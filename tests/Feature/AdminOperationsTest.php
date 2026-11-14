<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Models\Event;
use App\Models\EventDelivery;
use App\Models\Project;
use App\Models\Settings;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.owner_email' => 'owner@example.test']);
        $this->owner = User::factory()->create(['email' => 'owner@example.test']);
    }

    public function test_only_the_configured_owner_can_open_the_operations_panel(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->get('/admin/operations')
            ->assertForbidden();

        $this->get('/dashboard/users')->assertForbidden();

        $this->actingAs($this->owner)
            ->get('/admin/operations')
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Задания очереди');
    }

    public function test_owner_can_disable_and_enable_webhook_intake_and_processing(): void
    {
        $this->actingAs($this->owner)
            ->post('/admin/operations/state', ['state' => 'disabled'])
            ->assertRedirect('/admin/operations');

        $this->assertFalse(Settings::current()->webhook_ingress_enabled);
        $this->assertFalse(Settings::current()->webhook_processing_enabled);

        $this->post('/admin/operations/state', ['state' => 'enabled'])
            ->assertRedirect('/admin/operations');

        $this->assertTrue(Settings::current()->webhook_ingress_enabled);
        $this->assertTrue(Settings::current()->webhook_processing_enabled);
    }

    public function test_disabled_intake_returns_service_unavailable_without_storing_an_event(): void
    {
        Settings::current()->update(['webhook_ingress_enabled' => false]);
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create([
            'project_id' => $project->id,
            'auth_method' => 'none',
        ]);

        $this->postJson('/api/webhook/'.$endpoint->url_path, ['kind' => 'example'])
            ->assertStatus(503);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_paused_processing_defers_a_webhook_job_without_delivery_attempts(): void
    {
        Bus::fake();
        config(['queue.default' => 'database']);
        Settings::current()->update(['webhook_processing_enabled' => false]);
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create(['project_id' => $project->id]);
        $event = Event::create([
            'project_id' => $project->id,
            'webhook_endpoint_id' => $endpoint->id,
            'payload' => ['kind' => 'example'],
            'headers' => [],
            'status' => 'pending',
        ]);

        (new ProcessWebhookEvent($event))->handle();

        Bus::assertDispatched(ProcessWebhookEvent::class);
        $this->assertDatabaseCount('event_deliveries', 0);
    }

    public function test_admin_event_view_shows_incoming_and_outgoing_json(): void
    {
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create(['project_id' => $project->id]);
        $event = Event::create([
            'project_id' => $project->id,
            'webhook_endpoint_id' => $endpoint->id,
            'payload' => ['incoming' => 'payload'],
            'headers' => [],
        ]);
        EventDelivery::create([
            'event_id' => $event->id,
            'destination_url' => 'https://receiver.example.test/hook',
            'request_payload' => ['outgoing' => 'payload'],
            'attempt_number' => 1,
            'status' => 'success',
            'response_body' => '{"accepted":true}',
            'attempted_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get('/admin/operations/events/'.$event->id)
            ->assertOk()
            ->assertSee('incoming')
            ->assertSee('outgoing')
            ->assertSee('accepted');
    }

    public function test_delivery_job_persists_the_outgoing_payload(): void
    {
        Http::fake(['receiver.example.test/*' => Http::response(['accepted' => true], 200)]);
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create([
            'project_id' => $project->id,
            'destination_urls' => ['https://receiver.example.test/hook'],
            'auth_method' => 'none',
        ]);
        $event = Event::create([
            'project_id' => $project->id,
            'webhook_endpoint_id' => $endpoint->id,
            'payload' => ['sent' => 'exactly this'],
            'headers' => [],
        ]);

        $this->assertTrue(Settings::current()->webhook_processing_enabled);

        (new ProcessWebhookEvent($event))->handle();

        $this->assertSame(
            ['sent' => 'exactly this'],
            EventDelivery::firstOrFail()->request_payload
        );
    }
}
