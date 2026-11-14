@extends('layouts.master')

@section('title', 'Operations - HookBytes')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Operations</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Queue, webhook traffic, failures, and application logs.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.environment.index') }}" class="rounded-md bg-gray-700 px-4 py-2 font-semibold text-white hover:bg-gray-800">Подключения и .env</a>
            <form method="POST" action="{{ route('admin.operations.state') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $settings->webhook_ingress_enabled && $settings->webhook_processing_enabled ? 'disabled' : 'enabled' }}">
                <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white {{ $settings->webhook_ingress_enabled && $settings->webhook_processing_enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }}">
                    {{ $settings->webhook_ingress_enabled && $settings->webhook_processing_enabled ? 'Остановить вебхуки и очередь' : 'Включить вебхуки и очередь' }}
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Вебхуки', $settings->webhook_ingress_enabled ? 'Приём включён' : 'Приём отключён'],
            ['Обработка', $settings->webhook_processing_enabled ? 'Очередь включена' : 'Очередь на паузе'],
            ["Очередь: {$queueName}", $queuedCount],
            ['Задания с ошибкой', $failedJobCount],
            ['Ожидающие события', $pendingEventCount],
            ['События с ошибкой', $failedEventCount],
            ['В очереди failed', $failedQueueCount],
        ] as [$label, $value])
            <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Задания очереди</h2>
                @if (config('queue.default') !== 'database')
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Для драйвера {{ config('queue.default') }} показан общий размер очереди; Laravel не предоставляет список заданий через единый API.</p>
                @endif
            </div>
        </div>
        @if ($databaseJobs->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Нет доступных подробностей заданий. Для database-драйвера здесь показываются до 50 заданий; для Redis отображается только размер очереди.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-gray-500 dark:text-gray-400"><tr><th class="py-2 pr-4">Задание</th><th class="py-2 pr-4">Очередь</th><th class="py-2 pr-4">Попытки</th><th class="py-2 pr-4">Состояние</th><th class="py-2">Доступно (UTC)</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($databaseJobs as $job)
                        <tr class="text-gray-800 dark:text-gray-200">
                            <td class="py-3 pr-4">{{ $job['name'] }}</td><td class="py-3 pr-4">{{ $job['queue'] }}</td><td class="py-3 pr-4">{{ $job['attempts'] }}</td>
                            <td class="py-3 pr-4">{{ $job['reserved'] ? 'Выполняется' : 'Ожидает' }}</td>
                            <td class="py-3">{{ gmdate('Y-m-d H:i:s', $job['available_at']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Старейшие события в ожидании или обработке</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-gray-500 dark:text-gray-400"><tr><th class="py-2 pr-4">Событие</th><th class="py-2 pr-4">Проект / endpoint</th><th class="py-2 pr-4">Статус</th><th class="py-2">Получено</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($pendingEvents as $event)
                    <tr class="text-gray-800 dark:text-gray-200">
                        <td class="py-3 pr-4"><a class="text-blue-600 hover:underline" href="{{ route('admin.operations.events.show', $event) }}">{{ $event->event_id }}</a></td>
                        <td class="py-3 pr-4">{{ $event->project?->name }} / {{ $event->webhookEndpoint?->name }}</td>
                        <td class="py-3 pr-4">{{ $event->status }}</td><td class="py-3">{{ $event->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-500">Ожидающих событий нет.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Последние ошибки доставки</h2>
        <div class="space-y-3">
            @forelse ($recentFailures as $event)
                @php($lastDelivery = $event->deliveries->sortByDesc('attempted_at')->first())
                <a href="{{ route('admin.operations.events.show', $event) }}" class="block rounded border border-red-100 p-3 hover:bg-red-50 dark:border-red-900 dark:hover:bg-gray-700">
                    <div class="flex flex-wrap justify-between gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <span>{{ $event->event_id }} · {{ $event->project?->name }} / {{ $event->webhookEndpoint?->name }}</span>
                        <span>{{ $event->status }} · {{ $event->created_at }}</span>
                    </div>
                    @if ($lastDelivery?->error_message)
                        <p class="mt-2 break-words text-sm text-red-700 dark:text-red-300">{{ \Illuminate\Support\Str::limit($lastDelivery->error_message, 500) }}</p>
                    @elseif ($lastDelivery?->response_code)
                        <p class="mt-2 text-sm text-red-700 dark:text-red-300">Получен HTTP {{ $lastDelivery->response_code }}</p>
                    @endif
                </a>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Ошибок доставки нет.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Последние события</h2>
            <a href="{{ route('admin.operations.logs') }}" class="text-sm font-medium text-blue-600 hover:underline">Открыть логи</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-gray-500 dark:text-gray-400"><tr><th class="py-2 pr-4">ID</th><th class="py-2 pr-4">Проект / endpoint</th><th class="py-2 pr-4">Тип</th><th class="py-2 pr-4">Статус</th><th class="py-2">Получено</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recentEvents as $event)
                    <tr class="text-gray-800 dark:text-gray-200">
                        <td class="py-3 pr-4"><a class="text-blue-600 hover:underline" href="{{ route('admin.operations.events.show', $event) }}">{{ $event->event_id }}</a></td>
                        <td class="py-3 pr-4">{{ $event->project?->name }} / {{ $event->webhookEndpoint?->name }}</td>
                        <td class="py-3 pr-4">{{ $event->event_type ?? '—' }}</td><td class="py-3 pr-4">{{ $event->status }}</td><td class="py-3">{{ $event->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-gray-500">Событий пока нет.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Последние ошибки очереди</h2>
        @forelse ($failedJobs as $job)
            <details class="border-t border-gray-100 py-3 dark:border-gray-700">
                <summary class="cursor-pointer text-sm text-gray-800 dark:text-gray-200">{{ $job->queue }} · {{ $job->failed_at }} · {{ $job->uuid }}</summary>
                <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 text-xs text-red-800 dark:bg-gray-900 dark:text-red-200">{{ \Illuminate\Support\Str::limit($job->exception, 6000) }}</pre>
            </details>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Неудачных заданий нет.</p>
        @endforelse
    </section>
</div>
@endsection
