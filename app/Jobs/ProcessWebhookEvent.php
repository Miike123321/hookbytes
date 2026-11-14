<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventDelivery;
use App\Models\Settings;
use App\Services\CircuitBreakerService;
use App\Services\GatewayPolicyService;
use App\Services\PayloadTransformerService;
use App\Services\WebhookTransformationService;
use App\Services\WebhookRoutingService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 120, 600, 1800, 3600]; // 30s, 2m, 10m, 30m, 1h
    public $maxExceptions = 3;
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Event $event
    ) {
        $retryConfig = $event->webhookEndpoint->retry_config ?? [];
        if (isset($retryConfig['max_attempts'])) {
            $this->tries = max(1, min(100, (int) $retryConfig['max_attempts']));
            $this->maxExceptions = $this->tries;
        }

        if (isset($retryConfig['retry_delay'])) {
            $baseDelay = max(1, (int) $retryConfig['retry_delay']);
            $this->backoff = array_map(
                static fn (int $attempt): int => $baseDelay * (2 ** $attempt),
                range(0, max(0, $this->tries - 2))
            );
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!Settings::current()->webhook_processing_enabled) {
            if (config('queue.default') === 'sync') {
                return;
            }

            $this->deferWithoutAttempt(60);
            return;
        }

        if (in_array($this->event->status, ['delivered', 'dropped', 'deduplicated'], true)) {
            return;
        }

        $causalLock = null;
        try {
            $this->event->update([
                'status' => 'processing',
            ]);

            $endpoint = $this->event->webhookEndpoint;
            $policy = app(GatewayPolicyService::class)->forEndpoint($endpoint);

            $transformationService = app(WebhookTransformationService::class);
            $transformedPayload = $transformationService->applyTransformations(
                $endpoint,
                $this->event->payload,
                $this->event->headers ?? []
            );

            $routingService = app(WebhookRoutingService::class);
            $routingResult = $routingService->evaluateRouting(
                $endpoint,
                $transformedPayload,
                $this->event->headers ?? []
            );

            // Handle drop action
            if ($routingResult['action'] === 'drop') {
                $this->event->update([
                    'status' => 'dropped',
                    'delivered_at' => now(),
                ]);

                Log::info('Event dropped by routing rule', [
                    'event_id' => $this->event->event_id,
                    'rule_name' => $routingResult['rule']->name ?? 'Unknown',
                ]);
                return;
            }

            $destinations = $routingResult['destinations'];
            if (empty($destinations)) {
                throw new RuntimeException('Для события не настроен целевой маршрут.');
            }

            $payloadToSend = $policy['mappings'] !== []
                ? app(PayloadTransformerService::class)->transform($transformedPayload, $policy['mappings'])
                : $transformedPayload;

            if ($policy['causal_ordering_enabled']) {
                $entityId = data_get(
                    $this->event->payload,
                    $this->normalizeJsonPath($policy['causal_ordering_path'])
                );
                if (!is_scalar($entityId) || (string) $entityId === '') {
                    throw new RuntimeException('Не найден идентификатор сущности для последовательной доставки.');
                }

                $orderKey = "hookbytes:order:{$this->event->project_id}:" . hash('sha256', (string) $entityId);
                $lockTtl = max((int) $policy['causal_lock_ttl_seconds'], count($destinations) * 35);
                $causalLock = Cache::store('redis')->lock($orderKey, $lockTtl);
                if (!$causalLock->get()) {
                    $this->deferWithoutAttempt((int) $policy['causal_retry_delay_seconds']);
                    return;
                }

                $causalOrderKey = $this->event->causal_order_key
                    ?: hash('sha256', $this->event->project_id . ':' . (string) $entityId);
                $earlierEventPending = Event::query()
                    ->where('project_id', $this->event->project_id)
                    ->where('causal_order_key', $causalOrderKey)
                    ->where('id', '<', $this->event->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();
                if ($earlierEventPending) {
                    $causalLock->release();
                    $causalLock = null;
                    $this->deferWithoutAttempt((int) $policy['causal_retry_delay_seconds']);
                    return;
                }
            }

            foreach ($destinations as $destination) {
                $url = is_array($destination) ? $destination['url'] : $destination;
                if ($this->event->deliveries()
                    ->where('destination_url', $url)
                    ->where('status', 'success')
                    ->exists()) {
                    continue;
                }

                $circuitScope = $this->circuitScope($endpoint->project_id, $endpoint->id, $url);
                $probeLock = null;
                if ($policy['circuit_breaker_enabled']) {
                    $permit = app(CircuitBreakerService::class)->acquire(
                        $circuitScope,
                        (int) $policy['circuit_breaker_cooldown_seconds']
                    );
                    if (!$permit['allowed']) {
                        $this->deferWithoutAttempt((int) $permit['retry_after']);
                        return;
                    }
                    $probeLock = $permit['probe_lock'];
                }

                if ($policy['rate_limiting_enabled']) {
                    $rateLimited = false;
                    Redis::throttle('hookbytes:outbound:' . hash('sha256', $circuitScope))
                        ->block(0)
                        ->allow((int) $policy['outbound_rps'])
                        ->every(1)
                        ->then(
                            static function (): void {},
                            static function () use (&$rateLimited): void {
                                $rateLimited = true;
                            }
                        );

                    if ($rateLimited) {
                        app(CircuitBreakerService::class)->cancelProbe($circuitScope, $probeLock);
                        $this->deferWithoutAttempt(1);
                        return;
                    }
                }

                if (!$this->deliverToDestination($url, $payloadToSend, $policy, $circuitScope, $probeLock)) {
                    throw new RuntimeException("Не удалось доставить событие на {$url}.");
                }
            }

            $this->event->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'failed_at' => null,
            ]);

            Log::info('Event delivered successfully', [
                'event_id' => $this->event->event_id,
                'destinations' => count($destinations),
            ]);
        } catch (Throwable $exception) {
            Log::error('Event processing attempt failed', [
                'event_id' => $this->event->event_id,
                'error' => $exception->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $exception;
        } finally {
            $causalLock?->release();
        }
    }

    /**
     * Deliver event to a specific destination
     */
    private function deliverToDestination(
        string $url,
        array $payload,
        array $policy,
        string $circuitScope,
        ?Lock $probeLock
    ): bool
    {
        $startTime = microtime(true);
        $attemptNumber = $this->event->deliveries()->where('destination_url', $url)->count() + 1;
        $this->event->increment('delivery_attempts');
        $this->event->update(['last_attempt_at' => now()]);

        try {
            $headers = $this->buildHeaders($payload);
            
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($url, $payload);

            $latency = (int) ((microtime(true) - $startTime) * 1000);
            $isSuccess = $response->successful();

            EventDelivery::create([
                'event_id' => $this->event->id,
                'destination_url' => $url,
                'request_payload' => $payload,
                'attempt_number' => $attemptNumber,
                'status' => $isSuccess ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers(),
                'latency_ms' => $latency,
                'attempted_at' => now(),
            ]);

            if ($policy['circuit_breaker_enabled']) {
                $breaker = app(CircuitBreakerService::class);
                if ($response->status() >= 500) {
                    $breaker->recordFailure(
                        $circuitScope,
                        (int) $policy['circuit_breaker_threshold'],
                        (int) $policy['circuit_breaker_cooldown_seconds'],
                        $probeLock
                    );
                } else {
                    $breaker->recordSuccess($circuitScope, $probeLock);
                }
            }

            return $isSuccess;

        } catch (ConnectionException $exception) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);

            EventDelivery::create([
                'event_id' => $this->event->id,
                'destination_url' => $url,
                'request_payload' => $payload,
                'attempt_number' => $attemptNumber,
                'status' => 'failed',
                'response_code' => null,
                'response_body' => null,
                'response_headers' => [],
                'latency_ms' => $latency,
                'error_message' => $exception->getMessage(),
                'attempted_at' => now(),
            ]);

            if ($policy['circuit_breaker_enabled']) {
                app(CircuitBreakerService::class)->recordFailure(
                    $circuitScope,
                    (int) $policy['circuit_breaker_threshold'],
                    (int) $policy['circuit_breaker_cooldown_seconds'],
                    $probeLock
                );
            }

            return false;
        }
    }

    /**
     * Build headers for the webhook request
     */
    private function buildHeaders(array $payload): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hookbytes-Webhook-Gateway/1.0',
            'X-Event-ID' => $this->event->event_id,
            'X-Event-Type' => $this->event->event_type,
            'X-Delivery-Attempt' => (string) $this->event->delivery_attempts,
            'X-Timestamp' => $this->event->created_at->toISOString(),
        ];

        // Add custom headers from endpoint configuration
        $endpoint = $this->event->webhookEndpoint;
        if ($endpoint->headers_config) {
            $headers = array_merge($headers, $endpoint->headers_config);
        }

        // Add HMAC signature if configured
        if ($endpoint->auth_method === 'hmac' && $endpoint->auth_secret) {
            $payloadForSignature = json_encode($payload);
            $signature = 'sha256=' . hash_hmac('sha256', $payloadForSignature, $endpoint->auth_secret);
            $headers['X-Signature-256'] = $signature;
        }

        return $headers;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->event->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        Log::error('Webhook event processing failed permanently', [
            'event_id' => $this->event->event_id,
            'project_id' => $this->event->project_id,
            'endpoint_id' => $this->event->webhook_endpoint_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $policy = $this->event->webhookEndpoint->gatewayPolicy;
        if ($policy?->dlq_alert_enabled) {
            $latestDelivery = $this->event->deliveries()->latest('attempted_at')->first();
            $entityPath = $this->normalizeJsonPath((string) $policy->causal_ordering_path);

            SendDeadLetterAlert::dispatch($policy->id, [
                'project' => $this->event->project->name,
                'endpoint' => $this->event->webhookEndpoint->name,
                'event_id' => $this->event->event_id,
                'entity_id' => data_get($this->event->payload, $entityPath, '—'),
                'response_code' => $latestDelivery?->response_code ?? '—',
                'error' => substr(
                    (string) ($latestDelivery?->error_message ?: $latestDelivery?->response_body ?: $exception->getMessage()),
                    0,
                    500
                ),
                'replay_url' => route('dashboard.events.show', $this->event),
            ]);
        }

        $this->sendFailureNotifications();

        HandleFailedWebhookEvent::dispatch(
            $this->event,
            $exception->getMessage(),
            $this->attempts()
        );
    }

    private function deferWithoutAttempt(int $delaySeconds): void
    {
        if (config('queue.default') === 'sync') {
            throw new RuntimeException('Для отложенной доставки настройте асинхронную очередь.');
        }

        $deferredJob = new static($this->event);
        if ($this->connection) {
            $deferredJob->onConnection($this->connection);
        }
        if ($this->queue) {
            $deferredJob->onQueue($this->queue);
        }

        dispatch($deferredJob->delay(now()->addSeconds(max(1, $delaySeconds))));
        $this->delete();
    }

    private function normalizeJsonPath(string $path): string
    {
        return str_starts_with($path, '$.') ? substr($path, 2) : ltrim($path, '$');
    }

    private function circuitScope(int $projectId, int $endpointId, string $url): string
    {
        return "project:{$projectId}:endpoint:{$endpointId}:destination:" . hash('sha256', $url);
    }

    /**
     * Send failure notifications via Slack and/or email
     */
    private function sendFailureNotifications(): void
    {
        try {
            $settings = Settings::current();
            
            // Send Slack notification
            if ($settings->slack_notifications_enabled && $settings->slack_webhook_url) {
                $this->sendSlackNotification($settings->slack_webhook_url);
            }
            
            // Send email notification
            if ($settings->email_notifications_enabled && $settings->notification_email) {
                $this->sendEmailNotification($settings->notification_email);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send failure notifications', [
                'event_id' => $this->event->event_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(string $webhookUrl): void
    {
        $project = $this->event->project;
        $endpoint = $this->event->webhookEndpoint;
        
        $message = [
            'text' => '🚨 Webhook Event Failed',
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Event ID',
                            'value' => $this->event->event_id,
                            'short' => true
                        ],
                        [
                            'title' => 'Project',
                            'value' => $project->name,
                            'short' => true
                        ],
                        [
                            'title' => 'Endpoint',
                            'value' => $endpoint->name,
                            'short' => true
                        ],
                        [
                            'title' => 'Attempts',
                            'value' => $this->event->delivery_attempts,
                            'short' => true
                        ],
                        [
                            'title' => 'Failed At',
                            'value' => now()->format('Y-m-d H:i:s'),
                            'short' => false
                        ]
                    ]
                ]
            ]
        ];
        
        Http::timeout(10)->post($webhookUrl, $message);
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(string $email): void
    {
        $project = $this->event->project;
        $endpoint = $this->event->webhookEndpoint;
        
        $subject = 'Webhook Event Failed - ' . $project->name;
        $body = "A webhook event has failed delivery:\n\n";
        $body .= "Event ID: {$this->event->event_id}\n";
        $body .= "Project: {$project->name}\n";
        $body .= "Endpoint: {$endpoint->name}\n";
        $body .= "Delivery Attempts: {$this->event->delivery_attempts}\n";
        $body .= "Failed At: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $body .= "Please check the dashboard for more details.";
        
        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)
                   ->subject($subject);
        });
    }
}
