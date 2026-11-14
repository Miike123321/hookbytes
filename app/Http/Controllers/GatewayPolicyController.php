<?php

namespace App\Http\Controllers;

use App\Jobs\SendDeadLetterAlert;
use App\Models\WebhookEndpoint;
use App\Services\CircuitBreakerService;
use App\Services\GatewayPolicyService;
use App\Services\JsonSchemaValidationService;
use App\Services\PayloadTransformerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class GatewayPolicyController extends Controller
{
    public function __construct(
        private readonly GatewayPolicyService $policies,
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly PayloadTransformerService $transformer,
        private readonly JsonSchemaValidationService $schemaValidator
    ) {}

    public function edit(WebhookEndpoint $endpoint)
    {
        $policy = $endpoint->gatewayPolicy()->firstOrCreate(
            [],
            Arr::except($this->policies->defaults(), ['mappings'])
        );
        $circuitStates = collect($endpoint->destination_urls ?? [])
            ->mapWithKeys(fn (string $url) => [
                $url => $this->circuitBreaker->state($this->circuitScope($endpoint, $url)),
            ]);

        return view('dashboard.endpoints.gateway-policies', compact('endpoint', 'policy', 'circuitStates'));
    }

    public function update(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $messages = [
            'required' => 'Поле :attribute обязательно для заполнения.',
            'required_if' => 'Поле :attribute обязательно при выбранных настройках.',
            'string' => 'Поле :attribute должно быть строкой.',
            'boolean' => 'Поле :attribute должно иметь логическое значение.',
            'integer' => 'Поле :attribute должно быть целым числом.',
            'min' => 'Значение поля :attribute должно быть не меньше :min.',
            'max' => 'Значение поля :attribute не должно превышать :max.',
            'url' => 'Укажите корректный URL.',
            'in' => 'Выберите допустимое значение для поля :attribute.',
            'contract_schema.required_if' => 'Укажите JSON Schema, если проверка контракта включена.',
            'deduplication_source.in' => 'Выберите источник ключа дедупликации.',
            'deduplication_key.required_if' => 'Укажите имя заголовка или JSON Path ключа.',
            'deduplication_ttl.min' => 'TTL дедупликации должен быть не меньше 1 секунды.',
            'deduplication_response_status.in' => 'Для ответа на дубль допустимы только статусы 200 и 202.',
            'circuit_breaker_threshold.min' => 'Порог ошибок должен быть не меньше 1.',
            'circuit_breaker_cooldown_seconds.min' => 'Время охлаждения должно быть не меньше 1 секунды.',
            'outbound_rps.min' => 'Лимит исходящих запросов должен быть не меньше 1 RPS.',
            'causal_ordering_path.required_if' => 'Укажите JSON Path идентификатора сущности.',
            'causal_lock_ttl_seconds.min' => 'TTL блокировки должен быть не меньше 1 секунды.',
            'causal_retry_delay_seconds.min' => 'Задержка повтора должна быть не меньше 1 секунды.',
            'dlq_alert_channel.in' => 'Выберите Telegram или пользовательский JSON Webhook.',
            'dlq_webhook_url.url' => 'Укажите корректный URL пользовательского Webhook.',
        ];

        $data = $request->validate([
            'contract_validation_enabled' => ['nullable', 'boolean'],
            'contract_schema' => ['nullable', 'string', 'required_if:contract_validation_enabled,1'],
            'reject_unknown_fields' => ['nullable', 'boolean'],
            'deduplication_enabled' => ['nullable', 'boolean'],
            'deduplication_source' => ['required', Rule::in(['header', 'json_path'])],
            'deduplication_key' => ['nullable', 'string', 'max:255', 'required_if:deduplication_enabled,1'],
            'deduplication_ttl' => ['required', 'integer', 'min:1', 'max:2592000'],
            'deduplication_response_status' => ['required', Rule::in(['200', '202', 200, 202])],
            'circuit_breaker_enabled' => ['nullable', 'boolean'],
            'circuit_breaker_threshold' => ['required', 'integer', 'min:1', 'max:1000'],
            'circuit_breaker_cooldown_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'rate_limiting_enabled' => ['nullable', 'boolean'],
            'outbound_rps' => ['required', 'integer', 'min:1', 'max:10000'],
            'causal_ordering_enabled' => ['nullable', 'boolean'],
            'causal_ordering_path' => ['nullable', 'string', 'max:255', 'required_if:causal_ordering_enabled,1'],
            'causal_lock_ttl_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'causal_retry_delay_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'dlq_alert_enabled' => ['nullable', 'boolean'],
            'dlq_alert_channel' => ['required', Rule::in(['telegram', 'custom_webhook'])],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'dlq_webhook_url' => ['nullable', 'url', 'max:2048'],
            'dlq_message_template' => ['nullable', 'string', 'max:4000'],
        ], $messages);

        $schema = null;
        if (! empty($data['contract_schema'])) {
            $schema = json_decode($data['contract_schema'], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($schema)) {
                return back()
                    ->withInput()
                    ->withErrors(['contract_schema' => 'JSON Schema должен быть корректным JSON-объектом.']);
            }

            try {
                $this->schemaValidator->assertValidSchema(
                    $schema,
                    $request->boolean('reject_unknown_fields')
                );
            } catch (InvalidArgumentException|RuntimeException|JsonException) {
                return back()
                    ->withInput()
                    ->withErrors(['contract_schema' => 'JSON Schema содержит некорректное описание правил.']);
            }
        }

        $policy = $endpoint->gatewayPolicy()->firstOrNew();
        $token = $data['telegram_bot_token'] ?? null;
        unset($data['telegram_bot_token'], $data['contract_schema']);
        $data['contract_schema'] = $schema;

        foreach ([
            'contract_validation_enabled',
            'reject_unknown_fields',
            'deduplication_enabled',
            'circuit_breaker_enabled',
            'rate_limiting_enabled',
            'causal_ordering_enabled',
            'dlq_alert_enabled',
        ] as $checkbox) {
            $data[$checkbox] = $request->boolean($checkbox);
        }

        if ($token !== null && $token !== '') {
            $data['telegram_bot_token'] = $token;
        }

        $policy->fill($data);

        if ($policy->dlq_alert_enabled && $policy->dlq_alert_channel === 'telegram'
            && ! $policy->telegram_chat_id) {
            return back()
                ->withInput()
                ->withErrors(['telegram_chat_id' => 'Укажите Chat ID Telegram.']);
        }

        if ($policy->dlq_alert_enabled && $policy->dlq_alert_channel === 'custom_webhook'
            && ! $policy->dlq_webhook_url) {
            return back()
                ->withInput()
                ->withErrors(['dlq_webhook_url' => 'Укажите URL пользовательского Webhook.']);
        }

        if ($policy->dlq_alert_enabled && $policy->dlq_alert_channel === 'telegram'
            && ! $policy->telegram_bot_token) {
            return back()
                ->withInput()
                ->withErrors(['telegram_bot_token' => 'Для Telegram-алерта укажите Bot Token.']);
        }

        $policy->save();
        $this->policies->refresh($endpoint);

        return back()->with('success', 'Политики шлюза сохранены.');
    }

    public function resetCircuitBreaker(WebhookEndpoint $endpoint): RedirectResponse
    {
        foreach ($endpoint->destination_urls ?? [] as $url) {
            $this->circuitBreaker->reset($this->circuitScope($endpoint, $url));
        }

        return back()->with('success', 'Circuit Breaker сброшен для всех целевых маршрутов.');
    }

    public function sendTestAlert(WebhookEndpoint $endpoint): RedirectResponse
    {
        $policy = $endpoint->gatewayPolicy()->first();
        if (! $policy || ! $policy->dlq_alert_enabled) {
            return back()->withErrors(['dlq_alert_enabled' => 'Сначала включите и настройте DLQ-алерт.']);
        }

        SendDeadLetterAlert::dispatch($policy->id, [
            'project' => $endpoint->project->name,
            'endpoint' => $endpoint->name,
            'event_id' => 'TEST-'.now()->format('YmdHis'),
            'entity_id' => 'test-order',
            'response_code' => 503,
            'error' => 'Это тестовое сообщение DLQ.',
            'replay_url' => route('dashboard.projects.show', $endpoint->project),
        ]);

        return back()->with('success', 'Тестовый алерт поставлен в очередь отправки.');
    }

    public function editMappings(WebhookEndpoint $endpoint)
    {
        $mappings = $endpoint->payloadMappings()->orderBy('sort_order')->orderBy('id')->get();

        return view('dashboard.endpoints.payload-mappings', compact('endpoint', 'mappings'));
    }

    public function saveMappings(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $data = $request->validate([
            'mappings' => ['nullable', 'array', 'max:100'],
            'mappings.*.source_path' => ['required', 'string', 'max:255'],
            'mappings.*.target_path' => ['required', 'string', 'max:255'],
            'mappings.*.cast_type' => ['required', Rule::in(['string', 'int', 'float', 'bool', 'array', 'raw'])],
            'mappings.*.default_value' => ['nullable', 'string', 'max:10000'],
            'mappings.*.is_required' => ['nullable', 'boolean'],
        ], [
            'required' => 'Поле :attribute обязательно для заполнения.',
            'string' => 'Поле :attribute должно быть строкой.',
            'boolean' => 'Поле :attribute должно иметь логическое значение.',
            'max' => 'Значение поля :attribute не должно превышать :max.',
            'in' => 'Выберите допустимое значение для поля :attribute.',
            'mappings.*.source_path.required' => 'Укажите путь исходного поля.',
            'mappings.*.target_path.required' => 'Укажите путь целевого поля.',
            'mappings.*.cast_type.in' => 'Выбран неподдерживаемый тип преобразования.',
        ]);

        DB::transaction(function () use ($endpoint, $request, $data): void {
            $endpoint->payloadMappings()->delete();

            foreach ($data['mappings'] ?? [] as $index => $mapping) {
                $endpoint->payloadMappings()->create([
                    'source_path' => trim($mapping['source_path']),
                    'target_path' => trim($mapping['target_path']),
                    'cast_type' => $mapping['cast_type'],
                    'default_value' => $mapping['default_value'] ?? null,
                    'is_required' => $request->boolean("mappings.{$index}.is_required"),
                    'sort_order' => $index,
                ]);
            }
        });

        $this->policies->refresh($endpoint);

        return back()->with('success', 'Правила маппинга сохранены.');
    }

    public function previewMappings(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string'],
            'mappings' => ['nullable', 'array', 'max:100'],
            'mappings.*.source_path' => ['required', 'string', 'max:255'],
            'mappings.*.target_path' => ['required', 'string', 'max:255'],
            'mappings.*.cast_type' => ['required', Rule::in(['string', 'int', 'float', 'bool', 'array', 'raw'])],
            'mappings.*.default_value' => ['nullable', 'string', 'max:10000'],
            'mappings.*.is_required' => ['nullable', 'boolean'],
        ], [
            'required' => 'Поле :attribute обязательно для заполнения.',
            'string' => 'Поле :attribute должно быть строкой.',
            'boolean' => 'Поле :attribute должно иметь логическое значение.',
            'max' => 'Значение поля :attribute не должно превышать :max.',
            'in' => 'Выберите допустимое значение для поля :attribute.',
            'payload.required' => 'Введите пример JSON для проверки.',
            'mappings.*.source_path.required' => 'Укажите путь исходного поля.',
            'mappings.*.target_path.required' => 'Укажите путь целевого поля.',
            'mappings.*.cast_type.in' => 'Выбран неподдерживаемый тип преобразования.',
        ]);

        try {
            $payload = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new InvalidArgumentException('Корень JSON должен быть объектом или массивом.');
            }

            return response()->json([
                'payload' => $this->transformer->transform($payload, $data['mappings'] ?? []),
            ]);
        } catch (JsonException) {
            return response()->json([
                'message' => 'Не удалось преобразовать JSON.',
                'errors' => ['Пример JSON содержит синтаксическую ошибку.'],
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'Не удалось преобразовать JSON.',
                'errors' => [$exception->getMessage()],
            ], 422);
        }
    }

    private function circuitScope(WebhookEndpoint $endpoint, string $url): string
    {
        return "project:{$endpoint->project_id}:endpoint:{$endpoint->id}:destination:".hash('sha256', $url);
    }
}
