<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoint_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('contract_validation_enabled')->default(false);
            $table->json('contract_schema')->nullable();
            $table->boolean('reject_unknown_fields')->default(false);

            $table->boolean('deduplication_enabled')->default(false);
            $table->string('deduplication_source')->default('header');
            $table->string('deduplication_key')->default('X-Idempotency-Key');
            $table->unsignedInteger('deduplication_ttl')->default(86400);
            $table->unsignedSmallInteger('deduplication_response_status')->default(200);

            $table->boolean('circuit_breaker_enabled')->default(false);
            $table->unsignedSmallInteger('circuit_breaker_threshold')->default(5);
            $table->unsignedInteger('circuit_breaker_cooldown_seconds')->default(60);
            $table->boolean('rate_limiting_enabled')->default(false);
            $table->unsignedSmallInteger('outbound_rps')->default(10);

            $table->boolean('causal_ordering_enabled')->default(false);
            $table->string('causal_ordering_path')->default('order.id');
            $table->unsignedInteger('causal_lock_ttl_seconds')->default(60);
            $table->unsignedSmallInteger('causal_retry_delay_seconds')->default(5);

            $table->boolean('dlq_alert_enabled')->default(false);
            $table->string('dlq_alert_channel')->default('telegram');
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->text('dlq_webhook_url')->nullable();
            $table->text('dlq_message_template')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoint_policies');
    }
};
