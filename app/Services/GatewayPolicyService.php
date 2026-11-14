<?php

namespace App\Services;

use App\Models\WebhookEndpoint;
use App\Models\WebhookEndpointPolicy;
use Illuminate\Support\Facades\Cache;

class GatewayPolicyService
{
    private const CACHE_TTL_SECONDS = 300;

    public function forEndpoint(WebhookEndpoint $endpoint): array
    {
        if (! $endpoint->gateway_policy_active) {
            return $this->defaults();
        }

        return Cache::store('redis')->remember(
            $this->cacheKey($endpoint),
            self::CACHE_TTL_SECONDS,
            function () use ($endpoint): array {
                $policy = WebhookEndpointPolicy::query()
                    ->where('webhook_endpoint_id', $endpoint->id)
                    ->first();
                $settings = $policy ? $policy->toArray() : [];
                $settings['mappings'] = $endpoint->payloadMappings()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->toArray();

                return array_merge($this->defaults(), $settings);
            }
        );
    }

    public function forget(WebhookEndpoint $endpoint): void
    {
        Cache::store('redis')->forget($this->cacheKey($endpoint));
    }

    public function refresh(WebhookEndpoint $endpoint): void
    {
        $policy = $endpoint->gatewayPolicy()->first();
        $hasEnabledPolicy = $policy && (
            $policy->contract_validation_enabled
            || $policy->deduplication_enabled
            || $policy->circuit_breaker_enabled
            || $policy->rate_limiting_enabled
            || $policy->causal_ordering_enabled
            || $policy->dlq_alert_enabled
        );

        $endpoint->forceFill([
            'gateway_policy_active' => $hasEnabledPolicy || $endpoint->payloadMappings()->exists(),
        ])->save();
        $this->forget($endpoint);
    }

    public function defaults(): array
    {
        return [
            'contract_validation_enabled' => false,
            'contract_schema' => null,
            'reject_unknown_fields' => false,
            'deduplication_enabled' => false,
            'deduplication_source' => 'header',
            'deduplication_key' => 'X-Idempotency-Key',
            'deduplication_ttl' => 86400,
            'deduplication_response_status' => 200,
            'circuit_breaker_enabled' => false,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_cooldown_seconds' => 60,
            'rate_limiting_enabled' => false,
            'outbound_rps' => 10,
            'causal_ordering_enabled' => false,
            'causal_ordering_path' => 'order.id',
            'causal_lock_ttl_seconds' => 60,
            'causal_retry_delay_seconds' => 5,
            'dlq_alert_enabled' => false,
            'dlq_alert_channel' => 'telegram',
            'telegram_bot_token' => null,
            'telegram_chat_id' => null,
            'dlq_webhook_url' => null,
            'dlq_message_template' => null,
            'mappings' => [],
        ];
    }

    private function cacheKey(WebhookEndpoint $endpoint): string
    {
        return "hookbytes:gateway-policy:endpoint:{$endpoint->id}";
    }
}
