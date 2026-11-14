<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Jobs\SendDeadLetterAlert;
use App\Models\Event;
use App\Models\Project;
use App\Models\Settings;
use App\Models\WebhookEndpoint;
use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDeliveryReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_skips_destinations_that_already_succeeded(): void
    {
        Settings::current()->update(['webhook_processing_enabled' => true]);
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->noAuth()->create([
            'project_id' => $project->id,
            'destination_urls' => ['https://first.example.test/hook', 'https://second.example.test/hook'],
        ]);
        $event = Event::create([
            'project_id' => $project->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'order.created',
            'payload' => ['order' => ['id' => 42]],
            'headers' => [],
            'status' => 'pending',
        ]);

        $attempts = [];
        Http::fake(function (ClientRequest $request) use (&$attempts) {
            $attempts[$request->url()] = ($attempts[$request->url()] ?? 0) + 1;
            $status = str_contains($request->url(), 'second.example.test') && $attempts[$request->url()] === 1
                ? 503
                : 200;

            return Http::response('ok', $status);
        });

        $job = new ProcessWebhookEvent($event);
        try {
            $job->handle();
            self::fail('Первая попытка должна завершиться ошибкой второго получателя.');
        } catch (\RuntimeException) {
        }

        $job->handle();

        self::assertSame(1, $attempts['https://first.example.test/hook']);
        self::assertSame(2, $attempts['https://second.example.test/hook']);
        self::assertSame('delivered', $event->fresh()->status);
    }

    public function test_circuit_breaker_moves_through_open_and_half_open_states(): void
    {
        config(['cache.stores.redis.driver' => 'array']);
        Cache::purge('redis');
        $service = new CircuitBreakerService;
        $scope = 'project:1:endpoint:2:destination:test';

        self::assertTrue($service->acquire($scope, 3)['allowed']);
        $service->recordFailure($scope, 1, 3);
        self::assertSame('OPEN', $service->state($scope));
        self::assertFalse($service->acquire($scope, 3)['allowed']);

        $this->travel(4)->seconds();
        $probe = $service->acquire($scope, 3);
        self::assertTrue($probe['allowed']);
        self::assertSame('HALF_OPEN', $service->state($scope));

        $service->recordSuccess($scope, $probe['probe_lock']);
        self::assertSame('CLOSED', $service->state($scope));
    }

    public function test_dlq_alert_posts_rendered_template_to_custom_webhook(): void
    {
        $project = Project::factory()->create();
        $endpoint = WebhookEndpoint::factory()->noAuth()->create(['project_id' => $project->id]);
        $policy = $endpoint->gatewayPolicy()->create([
            'dlq_alert_enabled' => true,
            'dlq_alert_channel' => 'custom_webhook',
            'dlq_webhook_url' => 'https://alerts.example.test/incoming',
            'dlq_message_template' => '{{project}} {{event_id}} {{response_code}}',
        ]);
        Http::fake(['https://alerts.example.test/*' => Http::response(['ok' => true], 200)]);

        (new SendDeadLetterAlert($policy->id, [
            'project' => 'Тестовый проект',
            'event_id' => 'evt-42',
            'response_code' => 503,
        ]))->handle();

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://alerts.example.test/incoming'
            && $request['text'] === 'Тестовый проект evt-42 503');
    }
}
