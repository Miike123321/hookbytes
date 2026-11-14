<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Models\Project;
use App\Models\Settings;
use App\Models\WebhookEndpoint;
use App\Services\GatewayPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class WebhookReliabilityIngressTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::current()->update(['webhook_ingress_enabled' => true]);
        $this->project = Project::factory()->create(['is_active' => true]);
        $this->endpoint = WebhookEndpoint::factory()->noAuth()->create([
            'project_id' => $this->project->id,
            'url_path' => 'test/reliability',
        ]);
    }

    public function test_invalid_contract_is_rejected_before_event_creation_or_queue_dispatch(): void
    {
        $defaults = (new GatewayPolicyService)->defaults();
        $this->mock(GatewayPolicyService::class)
            ->shouldReceive('forEndpoint')
            ->andReturn(array_merge($defaults, [
                'contract_validation_enabled' => true,
                'contract_schema' => [
                    'type' => 'object',
                    'required' => ['order'],
                    'properties' => ['order' => ['type' => 'object']],
                ],
            ]));

        Queue::fake();

        $response = $this->postJson('/api/webhook/'.$this->endpoint->url_path, ['wrong' => true]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Входящий JSON не соответствует контракту.')
            ->assertJsonStructure(['errors' => [['path', 'keyword', 'message']]]);
        $this->assertDatabaseCount('events', 0);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_idempotency_key_is_logged_without_a_second_queue_job(): void
    {
        $defaults = (new GatewayPolicyService)->defaults();
        $this->mock(GatewayPolicyService::class)
            ->shouldReceive('forEndpoint')
            ->andReturn(array_merge($defaults, [
                'deduplication_enabled' => true,
                'deduplication_source' => 'header',
                'deduplication_key' => 'X-Idempotency-Key',
                'deduplication_ttl' => 86400,
                'deduplication_response_status' => 202,
            ]));

        Redis::shouldReceive('set')->twice()->andReturn('OK', null);
        Queue::fake();

        $first = $this->withHeader('X-Idempotency-Key', 'order-42')
            ->postJson('/api/webhook/'.$this->endpoint->url_path, ['order' => ['id' => 42]]);
        $second = $this->withHeader('X-Idempotency-Key', 'order-42')
            ->postJson('/api/webhook/'.$this->endpoint->url_path, ['order' => ['id' => 42]]);

        $first->assertOk();
        $second->assertAccepted()->assertJsonPath('status', 'deduplicated');
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseHas('events', ['status' => 'deduplicated']);
        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }
}
