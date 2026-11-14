<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Project;
use App\Models\WebhookEndpoint;
use App\Models\Settings;
use App\Jobs\ProcessWebhookEvent;
use App\Services\GatewayPolicyService;
use App\Services\JsonSchemaValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use JsonException;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook requests
     */
    public function handle(Request $request, string $urlPath)
    {
        return $this->receive($request, 'url_path', $urlPath);
    }

    /**
     * Authenticate the webhook request
     */
    private function authenticateRequest(Request $request, WebhookEndpoint $endpoint): bool
    {
        if ($endpoint->auth_method === 'none') {
            return true;
        }

        if ($endpoint->auth_method === 'hmac') {
            return $this->validateHmacSignature($request, $endpoint);
        }

        if ($endpoint->auth_method === 'shared_secret') {
            return $this->validateSharedSecret($request, $endpoint);
        }

        return false;
    }

    /**
     * Validate HMAC signature
     */
    private function validateHmacSignature(Request $request, WebhookEndpoint $endpoint): bool
    {
        $signature = $request->header('X-Signature-256') ?? $request->header('X-Hub-Signature-256');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $secret = $endpoint->auth_secret ?? '';
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate shared secret
     */
    private function validateSharedSecret(Request $request, WebhookEndpoint $endpoint): bool
    {
        $secret = $request->header('X-Webhook-Secret') ?? $request->input('secret');
        $expectedSecret = $endpoint->auth_secret ?? '';
        
        return hash_equals($expectedSecret, $secret ?? '');
    }

    /**
     * Create event record
     */
    private function createEvent(
        Request $request,
        Project $project,
        WebhookEndpoint $endpoint,
        ?string $causalOrderKey = null
    ): Event
    {
        return Event::create([
            'project_id' => $project->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => $request->header('X-Event-Type') ?? 'webhook',
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'pending',
            'causal_order_key' => $causalOrderKey,
        ]);
    }

    /**
     * Get webhook endpoint info (for debugging)
     */
    public function info(Request $request, string $urlPath)
    {
        $endpoint = WebhookEndpoint::where('url_path', $urlPath)
            ->with('project')
            ->first();
        
        if (!$endpoint) {
            return response()->json(['error' => 'Webhook endpoint not found'], 404);
        }

        return response()->json([
            'project' => $endpoint->project->name,
            'endpoint' => $endpoint->name,
            'url_path' => $endpoint->url_path,
            'short_url' => $endpoint->short_url,
            'auth_method' => $endpoint->auth_method,
            'is_active' => $endpoint->is_active,
            'destination_urls' => $endpoint->destination_urls,
        ]);
    }

    /**
     * Handle incoming webhook via short URL
     */
    public function handleShort(Request $request, string $shortUrl)
    {
        return $this->receive($request, 'short_url', $shortUrl);
    }

    /**
     * Get webhook endpoint info via short URL
     */
    public function infoShort(string $shortUrl)
    {
        $endpoint = WebhookEndpoint::where('short_url', $shortUrl)
            ->where('is_active', true)
            ->with('project')
            ->first();

        if (!$endpoint) {
            return response()->json(['error' => 'Webhook endpoint not found'], 404);
        }

        return response()->json([
            'project' => $endpoint->project->name,
            'endpoint' => $endpoint->name,
            'url_path' => $endpoint->url_path,
            'short_url' => $endpoint->short_url,
            'auth_method' => $endpoint->auth_method,
            'is_active' => $endpoint->is_active,
            'destination_urls' => $endpoint->destination_urls,
        ]);
    }

    private function receive(Request $request, string $lookupColumn, string $lookupValue)
    {
        if (!Settings::current()->webhook_ingress_enabled) {
            return response()->json(['error' => 'Приём вебхуков временно отключён.'], 503);
        }

        $endpoint = WebhookEndpoint::where($lookupColumn, $lookupValue)
            ->where('is_active', true)
            ->with('project')
            ->first();

        if (!$endpoint) {
            return response()->json(['error' => 'Маршрут вебхука не найден.'], 404);
        }

        $project = $endpoint->project;
        if (!$project->is_active) {
            return response()->json(['error' => 'Проект отключён.'], 404);
        }

        if (!$this->authenticateRequest($request, $endpoint)) {
            return response()->json(['error' => 'Не удалось проверить подлинность запроса.'], 401);
        }

        $policy = app(GatewayPolicyService::class)->forEndpoint($endpoint);
        $payload = $request->all();

        if ($policy['contract_validation_enabled']) {
            try {
                $jsonPayload = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return response()->json([
                    'message' => 'Тело запроса должно содержать корректный JSON.',
                    'errors' => [[
                        'path' => '#',
                        'keyword' => 'json',
                        'message' => 'Не удалось разобрать JSON.',
                    ]],
                ], 422);
            }

            $errors = app(JsonSchemaValidationService::class)->errors(
                $jsonPayload,
                $policy['contract_schema'] ?? [],
                $policy['reject_unknown_fields']
            );

            if ($errors !== []) {
                Log::warning('Webhook rejected by JSON Schema contract', [
                    'project_id' => $project->id,
                    'endpoint_id' => $endpoint->id,
                    'errors' => $errors,
                ]);

                return response()->json([
                    'message' => 'Входящий JSON не соответствует контракту.',
                    'errors' => $errors,
                ], 422);
            }
        }

        if ($policy['deduplication_enabled']) {
            $key = $policy['deduplication_source'] === 'header'
                ? $request->header($policy['deduplication_key'])
                : data_get($payload, $this->normalizeJsonPath($policy['deduplication_key']));

            if (!is_scalar($key) || trim((string) $key) === '') {
                return response()->json([
                    'message' => 'Не найден ключ дедупликации.',
                    'errors' => ['Укажите ключ в настроенном HTTP-заголовке или JSON Path.'],
                ], 422);
            }

            $redisKey = 'hookbytes:dedup:' . $project->id . ':' . hash('sha256', (string) $key);
            $reservation = Redis::set(
                $redisKey,
                '1',
                'EX',
                (int) $policy['deduplication_ttl'],
                'NX'
            );

            if ($reservation !== true && strtoupper((string) $reservation) !== 'OK') {
                $duplicate = $this->createEvent($request, $project, $endpoint);
                $duplicate->update(['status' => 'deduplicated']);

                Log::info('Webhook duplicate ignored', [
                    'project_id' => $project->id,
                    'endpoint_id' => $endpoint->id,
                    'event_id' => $duplicate->event_id,
                    'status' => 'deduplicated',
                ]);

                return response()->json([
                    'success' => true,
                    'status' => 'deduplicated',
                    'event_id' => $duplicate->event_id,
                    'message' => 'Повторное событие уже было принято и не будет обработано повторно.',
                ], (int) $policy['deduplication_response_status']);
            }
        }

        $causalOrderKey = null;
        if ($policy['causal_ordering_enabled']) {
            $entityId = data_get($payload, $this->normalizeJsonPath($policy['causal_ordering_path']));
            if (!is_scalar($entityId) || (string) $entityId === '') {
                return response()->json([
                    'message' => 'Не найден идентификатор сущности для последовательной доставки.',
                    'errors' => ['Проверьте настроенный JSON Path.'],
                ], 422);
            }

            $causalOrderKey = hash('sha256', $project->id . ':' . (string) $entityId);
        }

        $event = $this->createEvent($request, $project, $endpoint, $causalOrderKey);
        ProcessWebhookEvent::dispatch($event);

        Log::info('Webhook received', [
            'project_id' => $project->id,
            'endpoint_id' => $endpoint->id,
            'event_id' => $event->event_id,
        ]);

        return response()->json([
            'success' => true,
            'event_id' => $event->event_id,
            'message' => 'Вебхук принят и поставлен в очередь.',
        ], 200);
    }

    private function normalizeJsonPath(string $path): string
    {
        return str_starts_with($path, '$.') ? substr($path, 2) : ltrim($path, '$');
    }
}
