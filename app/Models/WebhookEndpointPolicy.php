<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEndpointPolicy extends Model
{
    protected $fillable = [
        'webhook_endpoint_id',
        'contract_validation_enabled',
        'contract_schema',
        'reject_unknown_fields',
        'deduplication_enabled',
        'deduplication_source',
        'deduplication_key',
        'deduplication_ttl',
        'deduplication_response_status',
        'circuit_breaker_enabled',
        'circuit_breaker_threshold',
        'circuit_breaker_cooldown_seconds',
        'rate_limiting_enabled',
        'outbound_rps',
        'causal_ordering_enabled',
        'causal_ordering_path',
        'causal_lock_ttl_seconds',
        'causal_retry_delay_seconds',
        'dlq_alert_enabled',
        'dlq_alert_channel',
        'telegram_bot_token',
        'telegram_chat_id',
        'dlq_webhook_url',
        'dlq_message_template',
    ];

    protected $hidden = [
        'telegram_bot_token',
        'dlq_webhook_url',
    ];

    protected function casts(): array
    {
        return [
            'contract_validation_enabled' => 'boolean',
            'contract_schema' => 'array',
            'reject_unknown_fields' => 'boolean',
            'deduplication_enabled' => 'boolean',
            'deduplication_ttl' => 'integer',
            'deduplication_response_status' => 'integer',
            'circuit_breaker_enabled' => 'boolean',
            'circuit_breaker_threshold' => 'integer',
            'circuit_breaker_cooldown_seconds' => 'integer',
            'rate_limiting_enabled' => 'boolean',
            'outbound_rps' => 'integer',
            'causal_ordering_enabled' => 'boolean',
            'causal_lock_ttl_seconds' => 'integer',
            'causal_retry_delay_seconds' => 'integer',
            'dlq_alert_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
            'dlq_webhook_url' => 'encrypted',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
