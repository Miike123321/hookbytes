@extends('layouts.master')

@section('title', 'Webhook Event - HookBytes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Webhook event</h1>
            <p class="mt-1 font-mono text-sm text-gray-600 dark:text-gray-300">{{ $event->event_id }}</p>
        </div>
        <a href="{{ route('admin.operations.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800 hover:bg-gray-300">Назад к панели</a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Проект', $event->project?->name ?? 'Удалён'],
            ['Endpoint', $event->webhookEndpoint?->name ?? 'Удалён'],
            ['Тип', $event->event_type ?? '—'],
            ['Статус', $event->status],
        ] as [$label, $value])
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-gray-900 dark:text-white">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Входящий JSON</h2>
        <pre class="max-h-[32rem] overflow-auto rounded bg-gray-50 p-4 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        <h3 class="mb-2 mt-5 font-semibold text-gray-900 dark:text-white">Заголовки входящего запроса</h3>
        <pre class="max-h-64 overflow-auto rounded bg-gray-50 p-4 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ json_encode($event->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </section>

    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Исходящие доставки</h2>
        @forelse ($event->deliveries as $delivery)
            <article class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <div class="mb-4 flex flex-wrap justify-between gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ $delivery->destination_url }}</span>
                    <span>Попытка {{ $delivery->attempt_number }} · {{ $delivery->status }} · HTTP {{ $delivery->response_code ?? '—' }} · {{ $delivery->attempted_at }}</span>
                </div>
                @if ($delivery->error_message)
                    <p class="mb-3 rounded bg-red-50 p-3 text-sm text-red-800">{{ $delivery->error_message }}</p>
                @endif
                <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">Отправленный JSON</h3>
                <pre class="max-h-96 overflow-auto rounded bg-gray-50 p-4 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ $delivery->request_payload !== null ? json_encode($delivery->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'Данные не сохранялись для этой попытки.' }}</pre>
                <h3 class="mb-2 mt-4 font-semibold text-gray-900 dark:text-white">Ответ получателя</h3>
                <pre class="max-h-64 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-4 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ $delivery->response_body ?? 'Ответ отсутствует.' }}</pre>
            </article>
        @empty
            <div class="rounded-lg bg-white p-5 text-sm text-gray-500 shadow dark:bg-gray-800">Попыток доставки пока нет.</div>
        @endforelse
    </section>
</div>
@endsection
