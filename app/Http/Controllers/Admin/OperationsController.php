<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;

class OperationsController extends Controller
{
    public function index(): View
    {
        $settings = Settings::current();
        $connectionName = config('queue.default');
        $queueName = config("queue.connections.{$connectionName}.queue", 'default');
        $queue = Queue::connection($connectionName);
        $databaseJobs = $this->databaseJobs($connectionName, $queueName);
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->latest('failed_at')->limit(20)->get()
            : collect();

        return view('admin.operations.index', [
            'settings' => $settings,
            'queueName' => $queueName,
            'queuedCount' => $queue->size($queueName),
            'failedQueueCount' => $queue->size('failed'),
            'failedJobCount' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'pendingEventCount' => Event::whereIn('status', ['pending', 'processing'])->count(),
            'failedEventCount' => Event::whereIn('status', ['failed', 'permanently_failed'])->count(),
            'databaseJobs' => $databaseJobs,
            'failedJobs' => $failedJobs,
            'pendingEvents' => Event::with(['project', 'webhookEndpoint'])
                ->whereIn('status', ['pending', 'processing'])
                ->oldest()
                ->limit(50)
                ->get(),
            'recentFailures' => Event::with(['project', 'webhookEndpoint', 'deliveries'])
                ->whereIn('status', ['failed', 'permanently_failed'])
                ->latest()
                ->limit(20)
                ->get(),
            'recentEvents' => Event::with(['project', 'webhookEndpoint'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateState(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'in:enabled,disabled'],
        ]);

        $enabled = $validated['state'] === 'enabled';
        $settings = Settings::current();
        $settings->update([
            'webhook_ingress_enabled' => $enabled,
            'webhook_processing_enabled' => $enabled,
        ]);

        return redirect()->route('admin.operations.index')
            ->with('success', $enabled
                ? 'Приём вебхуков и обработка очереди включены.'
                : 'Приём вебхуков отключён. Обработка очереди приостановлена.');
    }

    public function showEvent(Event $event): View
    {
        $event->load(['project', 'webhookEndpoint', 'deliveries' => function ($query) {
            $query->latest('attempted_at');
        }]);

        return view('admin.operations.event', compact('event'));
    }

    public function logs(Request $request): View
    {
        $logDirectory = storage_path('logs');
        $files = collect(is_dir($logDirectory) ? File::files($logDirectory) : [])
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $requestedName = $request->query('file');
        $selectedFile = $requestedName
            ? $files->first(fn ($file) => $file->getFilename() === $requestedName)
            : $files->first();

        abort_if($requestedName && !$selectedFile, 404);

        $lines = $selectedFile
            ? $this->tail($selectedFile->getPathname(), 200)
            : [];

        return view('admin.operations.logs', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'lines' => $lines,
        ]);
    }

    private function databaseJobs(string $connectionName, string $queueName): Collection
    {
        if ($connectionName !== 'database' || !Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->where('queue', $queueName)
            ->orderBy('available_at')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'queue' => $job->queue,
                    'name' => is_array($payload) ? ($payload['displayName'] ?? 'Unknown job') : 'Unknown job',
                    'attempts' => $job->attempts,
                    'available_at' => $job->available_at,
                    'reserved' => $job->reserved_at !== null,
                ];
            });
    }

    private function tail(string $path, int $lineLimit): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to read log file: {$path}");
        }

        $size = filesize($path);
        $offset = max(0, $size - 65536);
        fseek($handle, $offset);

        if ($offset > 0) {
            fgets($handle);
        }

        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new RuntimeException("Unable to read log file: {$path}");
        }

        return array_slice(explode("\n", rtrim($contents)), -$lineLimit);
    }
}
