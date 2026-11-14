<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GatewayPolicyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_gateway_policies_and_preview_payload_mappings(): void
    {
        config([
            'admin.owner_email' => 'owner@example.test',
            'cache.stores.redis.driver' => 'array',
        ]);
        Cache::purge('redis');
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'email_verified_at' => now(),
        ]);
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->noAuth()->create(['project_id' => $project->id]);

        $this->actingAs($owner)
            ->get(route('dashboard.endpoints.gateway', $endpoint))
            ->assertOk()
            ->assertSee('Политики шлюза и надежность');

        $this->put(route('dashboard.endpoints.gateway.update', $endpoint), [
            'contract_validation_enabled' => '1',
            'contract_schema' => '{"type":"object","properties":{"order":{"type":"object"}}}',
            'reject_unknown_fields' => '1',
            'deduplication_source' => 'header',
            'deduplication_key' => 'X-Idempotency-Key',
            'deduplication_ttl' => 86400,
            'deduplication_response_status' => 200,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_cooldown_seconds' => 60,
            'outbound_rps' => 10,
            'causal_ordering_path' => 'order.id',
            'causal_lock_ttl_seconds' => 60,
            'causal_retry_delay_seconds' => 5,
            'dlq_alert_channel' => 'telegram',
            'telegram_chat_id' => '',
            'dlq_webhook_url' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('webhook_endpoint_policies', [
            'webhook_endpoint_id' => $endpoint->id,
            'contract_validation_enabled' => true,
            'reject_unknown_fields' => true,
        ]);
        $this->assertTrue($endpoint->fresh()->gateway_policy_active);

        $this->post(route('dashboard.endpoints.mappings.save', $endpoint), [
            'mappings' => [[
                'source_path' => 'order.id',
                'target_path' => 'id',
                'cast_type' => 'int',
                'default_value' => null,
                'is_required' => '1',
            ]],
        ])->assertRedirect();

        $this->get(route('dashboard.endpoints.mappings', $endpoint))
            ->assertOk()
            ->assertSee('Проверить трансформацию');

        $this->postJson(route('dashboard.endpoints.mappings.preview', $endpoint), [
            'payload' => '{"order":{"id":"42"}}',
            'mappings' => [[
                'source_path' => 'order.id',
                'target_path' => 'id',
                'cast_type' => 'int',
                'is_required' => true,
            ]],
        ])->assertOk()->assertExactJson(['payload' => ['id' => 42]]);

        $this->postJson(route('dashboard.endpoints.mappings.preview', $endpoint), [
            'payload' => '{broken',
            'mappings' => [],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0', 'Пример JSON содержит синтаксическую ошибку.');
    }
}
