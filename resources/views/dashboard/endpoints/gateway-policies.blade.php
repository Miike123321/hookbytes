@extends('layouts.master')

@section('title', 'Политики шлюза')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('dashboard.endpoints.edit', $endpoint) }}" class="text-sm text-indigo-600 hover:underline">← К настройкам маршрута</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Политики шлюза и надежность</h1>
        <p class="text-gray-600">{{ $endpoint->project->name }} / {{ $endpoint->name }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('dashboard.endpoints.gateway', $endpoint) }}" class="rounded bg-indigo-600 px-3 py-2 text-white">Политики шлюза</a>
            <a href="{{ route('dashboard.endpoints.mappings', $endpoint) }}" class="rounded bg-gray-700 px-3 py-2 text-white">Маппинг Payload</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded border border-green-300 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded border border-red-300 bg-red-50 p-4 text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('dashboard.endpoints.gateway.update', $endpoint) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">1. Проверка JSON-контракта</h2>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="contract_validation_enabled" value="1" @checked(old('contract_validation_enabled', $policy->contract_validation_enabled))> Включить синхронную проверку до очереди</label>
            <label class="mt-4 block text-sm font-medium">JSON Schema</label>
            <textarea id="contract-schema" name="contract_schema" rows="12" class="mt-1 w-full rounded border-gray-300 font-mono text-sm" spellcheck="false">{{ old('contract_schema', $policy->contract_schema ? json_encode($policy->contract_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            <p id="schema-status" class="mt-1 text-sm text-gray-500">Синтаксис JSON проверяется при вводе и на сервере.</p>
            <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="reject_unknown_fields" value="1" @checked(old('reject_unknown_fields', $policy->reject_unknown_fields))> Строгий режим: запрещать неизвестные поля</label>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">2. Дедупликация входящих событий</h2>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="deduplication_enabled" value="1" @checked(old('deduplication_enabled', $policy->deduplication_enabled))> Включить дедупликацию</label>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm">Источник ключа
                    <select name="deduplication_source" class="mt-1 w-full rounded border-gray-300">
                        <option value="header" @selected(old('deduplication_source', $policy->deduplication_source) === 'header')>HTTP-заголовок</option>
                        <option value="json_path" @selected(old('deduplication_source', $policy->deduplication_source) === 'json_path')>Путь в JSON</option>
                    </select>
                </label>
                <label class="block text-sm">Имя заголовка / JSON Path
                    <input name="deduplication_key" value="{{ old('deduplication_key', $policy->deduplication_key) }}" class="mt-1 w-full rounded border-gray-300" placeholder="X-Idempotency-Key или order.id">
                </label>
                <label class="block text-sm">TTL (секунды)
                    <input type="number" min="1" max="2592000" name="deduplication_ttl" value="{{ old('deduplication_ttl', $policy->deduplication_ttl) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">HTTP-статус для дубля
                    <select name="deduplication_response_status" class="mt-1 w-full rounded border-gray-300">
                        <option value="200" @selected((int) old('deduplication_response_status', $policy->deduplication_response_status) === 200)>200 OK</option>
                        <option value="202" @selected((int) old('deduplication_response_status', $policy->deduplication_response_status) === 202)>202 Accepted</option>
                    </select>
                </label>
            </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">4. Circuit Breaker и исходящий RPS</h2>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="circuit_breaker_enabled" value="1" @checked(old('circuit_breaker_enabled', $policy->circuit_breaker_enabled))> Включить Circuit Breaker для целевых URL</label>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="block text-sm">Порог подряд ошибок 5xx / таймаутов
                    <input type="number" min="1" name="circuit_breaker_threshold" value="{{ old('circuit_breaker_threshold', $policy->circuit_breaker_threshold) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">Охлаждение (секунды)
                    <input type="number" min="1" name="circuit_breaker_cooldown_seconds" value="{{ old('circuit_breaker_cooldown_seconds', $policy->circuit_breaker_cooldown_seconds) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">Исходящий лимит (RPS)
                    <input type="number" min="1" name="outbound_rps" value="{{ old('outbound_rps', $policy->outbound_rps) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
            </div>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="rate_limiting_enabled" value="1" @checked(old('rate_limiting_enabled', $policy->rate_limiting_enabled))> Ограничивать исходящие запросы в секунду</label>
            <div class="mt-5 space-y-2">
                <h3 class="font-medium">Состояние по целевым URL</h3>
                @forelse ($circuitStates as $url => $state)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3">
                        <code class="break-all text-sm">{{ $url }}</code>
                        <span class="rounded px-2 py-1 text-xs font-semibold {{ $state === 'OPEN' ? 'bg-red-100 text-red-800' : ($state === 'HALF_OPEN' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">{{ $state }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">У маршрута пока нет целевых URL.</p>
                @endforelse
                <button formaction="{{ route('dashboard.endpoints.gateway.circuit-breaker.reset', $endpoint) }}" formmethod="POST" formnovalidate class="mt-2 rounded bg-amber-600 px-3 py-2 text-white" onclick="this.form.querySelector('[name=_method]').disabled=true">Сбросить Circuit Breaker</button>
            </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">5. Порядок событий по сущности</h2>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="causal_ordering_enabled" value="1" @checked(old('causal_ordering_enabled', $policy->causal_ordering_enabled))> Последовательно обрабатывать события одного заказа</label>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="block text-sm">JSON Path ID сущности
                    <input name="causal_ordering_path" value="{{ old('causal_ordering_path', $policy->causal_ordering_path) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">TTL блокировки (секунды)
                    <input type="number" min="1" name="causal_lock_ttl_seconds" value="{{ old('causal_lock_ttl_seconds', $policy->causal_lock_ttl_seconds) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">Задержка повторной постановки (секунды)
                    <input type="number" min="1" name="causal_retry_delay_seconds" value="{{ old('causal_retry_delay_seconds', $policy->causal_retry_delay_seconds) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
            </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">6. Алерты Dead Letter Queue</h2>
            <label class="mt-4 flex items-center gap-2"><input type="checkbox" name="dlq_alert_enabled" value="1" @checked(old('dlq_alert_enabled', $policy->dlq_alert_enabled))> Включить уведомления при окончательном отказе</label>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm">Канал
                    <select name="dlq_alert_channel" class="mt-1 w-full rounded border-gray-300">
                        <option value="telegram" @selected(old('dlq_alert_channel', $policy->dlq_alert_channel) === 'telegram')>Telegram</option>
                        <option value="custom_webhook" @selected(old('dlq_alert_channel', $policy->dlq_alert_channel) === 'custom_webhook')>Custom JSON Webhook</option>
                    </select>
                </label>
                <label class="block text-sm">Telegram Chat ID
                    <input name="telegram_chat_id" value="{{ old('telegram_chat_id', $policy->telegram_chat_id) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
                <label class="block text-sm">Bot Token (хранится зашифрованным)
                    <input type="password" name="telegram_bot_token" autocomplete="new-password" class="mt-1 w-full rounded border-gray-300" placeholder="{{ $policy->telegram_bot_token ? 'Токен задан; пустое поле оставит его без изменений' : 'Введите Bot Token' }}">
                </label>
                <label class="block text-sm">URL пользовательского Webhook
                    <input type="url" name="dlq_webhook_url" value="{{ old('dlq_webhook_url', $policy->dlq_webhook_url) }}" class="mt-1 w-full rounded border-gray-300">
                </label>
            </div>
            <label class="mt-4 block text-sm">Шаблон сообщения
                <textarea name="dlq_message_template" rows="5" class="mt-1 w-full rounded border-gray-300 font-mono text-sm" placeholder="Например: проект, маршрут, event_id, entity_id, response_code, error, replay_url">{{ old('dlq_message_template', $policy->dlq_message_template) }}</textarea>
            </label>
            <button formaction="{{ route('dashboard.endpoints.gateway.test-alert', $endpoint) }}" formmethod="POST" formnovalidate class="mt-4 rounded bg-blue-600 px-3 py-2 text-white" onclick="this.form.querySelector('[name=_method]').disabled=true">Отправить тестовый алерт</button>
        </section>

        <button class="rounded bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">Сохранить политики</button>
    </form>
</div>
<script>
    const schemaInput = document.getElementById('contract-schema');
    const schemaStatus = document.getElementById('schema-status');
    const checkSchema = () => {
        try {
            JSON.parse(schemaInput.value);
            schemaStatus.textContent = 'Синтаксис JSON корректен.';
            schemaStatus.className = 'mt-1 text-sm text-green-700';
        } catch {
            schemaStatus.textContent = 'Ошибка синтаксиса JSON.';
            schemaStatus.className = 'mt-1 text-sm text-red-700';
        }
    };
    schemaInput.addEventListener('input', checkSchema);
    if (schemaInput.value.trim()) checkSchema();
</script>
@endsection
