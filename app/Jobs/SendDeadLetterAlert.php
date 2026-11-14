<?php

namespace App\Jobs;

use App\Models\WebhookEndpointPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendDeadLetterAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $policyId,
        public array $context
    ) {}

    public function handle(): void
    {
        $policy = WebhookEndpointPolicy::findOrFail($this->policyId);
        if (! $policy->dlq_alert_enabled) {
            return;
        }

        $message = $this->renderMessage($policy->dlq_message_template, $this->context);
        $http = Http::timeout(10)->withoutRedirecting();

        if ($policy->dlq_alert_channel === 'telegram') {
            $response = $http->post(
                'https://api.telegram.org/bot'.$policy->telegram_bot_token.'/sendMessage',
                [
                    'chat_id' => $policy->telegram_chat_id,
                    'text' => $message,
                ]
            );
        } else {
            $response = $http->post($policy->dlq_webhook_url, [
                'text' => $message,
                'event' => $this->context,
            ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Удалённый сервис отклонил DLQ-алерт: HTTP '.$response->status());
        }
    }

    private function renderMessage(?string $template, array $context): string
    {
        $template ??= "Webhook завершился ошибкой\nПроект: {{project}}\nМаршрут: {{endpoint}}\nЗаказ: {{entity_id}}\nHTTP: {{response_code}}\nОшибка: {{error}}\nReplay: {{replay_url}}";
        $replacements = [];

        foreach (['project', 'endpoint', 'event_id', 'entity_id', 'response_code', 'error', 'replay_url'] as $name) {
            $replacements['{{'.$name.'}}'] = (string) ($context[$name] ?? '—');
        }

        return strtr($template, $replacements);
    }
}
