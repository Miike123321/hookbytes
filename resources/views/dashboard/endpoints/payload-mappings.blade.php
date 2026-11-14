@extends('layouts.master')

@section('title', 'Маппинг Payload')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('dashboard.endpoints.edit', $endpoint) }}" class="text-sm text-indigo-600 hover:underline">← К настройкам маршрута</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Трансформация и маппинг Payload</h1>
        <p class="text-gray-600">{{ $endpoint->project->name }} / {{ $endpoint->name }}. Исходный payload события сохраняется без изменений.</p>
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

    <form method="POST" action="{{ route('dashboard.endpoints.mappings.save', $endpoint) }}" class="rounded-lg bg-white p-6 shadow">
        @csrf
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Упорядоченные правила</h2>
                <p class="text-sm text-gray-500">Пути поддерживают dot-notation и запись $.order.id.</p>
            </div>
            <button type="button" id="add-mapping" class="rounded bg-green-600 px-3 py-2 text-white">Добавить правило</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-gray-50 text-xs uppercase text-gray-600">
                    <tr><th class="p-2">Исходный путь</th><th class="p-2">Целевой путь</th><th class="p-2">Тип</th><th class="p-2">Значение по умолчанию</th><th class="p-2">Обязательное</th><th class="p-2">Порядок</th><th class="p-2">Действия</th></tr>
                </thead>
                <tbody id="mapping-rows" class="divide-y">
                    @foreach (old('mappings', $mappings->toArray()) as $index => $mapping)
                        <tr class="mapping-row">
                            <td class="p-2"><input required name="mappings[{{ $index }}][source_path]" value="{{ $mapping['source_path'] ?? '' }}" class="w-40 rounded border-gray-300" placeholder="order.id"></td>
                            <td class="p-2"><input required name="mappings[{{ $index }}][target_path]" value="{{ $mapping['target_path'] ?? '' }}" class="w-40 rounded border-gray-300" placeholder="id"></td>
                            <td class="p-2"><select name="mappings[{{ $index }}][cast_type]" class="rounded border-gray-300">
                                @foreach (['raw' => 'raw', 'string' => 'string', 'int' => 'int', 'float' => 'float', 'bool' => 'bool', 'array' => 'array'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($mapping['cast_type'] ?? 'raw') === $value)>{{ $label }}</option>
                                @endforeach
                            </select></td>
                            <td class="p-2"><input name="mappings[{{ $index }}][default_value]" value="{{ $mapping['default_value'] ?? '' }}" class="w-40 rounded border-gray-300" placeholder="не задано"></td>
                            <td class="p-2 text-center"><input type="checkbox" name="mappings[{{ $index }}][is_required]" value="1" @checked($mapping['is_required'] ?? false)></td>
                            <td class="p-2 whitespace-nowrap"><button type="button" class="move-up rounded border px-2 py-1">↑</button> <button type="button" class="move-down rounded border px-2 py-1">↓</button></td>
                            <td class="p-2"><button type="button" class="remove-mapping rounded bg-red-600 px-2 py-1 text-white">Удалить</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button class="mt-5 rounded bg-indigo-600 px-5 py-3 font-semibold text-white">Сохранить правила</button>
    </form>

    <section class="mt-6 rounded-lg bg-white p-6 shadow">
        <h2 class="text-lg font-semibold">Live-песочница</h2>
        <p class="mb-3 text-sm text-gray-500">Песочница применяет текущие несохраненные правила без отправки HTTP-запросов.</p>
        <label class="block text-sm font-medium" for="sample-payload">Пример JSON от партнера</label>
        <textarea id="sample-payload" rows="10" class="mt-1 w-full rounded border-gray-300 font-mono text-sm">{
  "order": {"id": 123, "total": "49.95"},
  "customer": {"email": "buyer@example.test"}
}</textarea>
        <button type="button" id="preview-mapping" class="mt-3 rounded bg-blue-600 px-4 py-2 text-white">Проверить трансформацию</button>
        <p id="preview-error" class="mt-3 text-sm text-red-700"></p>
        <label class="mt-4 block text-sm font-medium" for="preview-output">Итоговый JSON для системы</label>
        <pre id="preview-output" class="mt-1 min-h-24 overflow-auto rounded bg-gray-900 p-4 text-sm text-green-200">{}</pre>
    </section>
</div>
<script>
    const rows = document.getElementById('mapping-rows');
    const addButton = document.getElementById('add-mapping');

    function renumberRows() {
        [...rows.querySelectorAll('.mapping-row')].forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/mappings\[\d+\]/, `mappings[${index}]`);
            });
        });
    }

    function addRow(rule = {}) {
        const index = rows.querySelectorAll('.mapping-row').length;
        const row = document.createElement('tr');
        row.className = 'mapping-row';
        row.innerHTML = `
            <td class="p-2"><input required name="mappings[${index}][source_path]" class="w-40 rounded border-gray-300" placeholder="order.id"></td>
            <td class="p-2"><input required name="mappings[${index}][target_path]" class="w-40 rounded border-gray-300" placeholder="id"></td>
            <td class="p-2"><select name="mappings[${index}][cast_type]" class="rounded border-gray-300"><option value="raw">raw</option><option value="string">string</option><option value="int">int</option><option value="float">float</option><option value="bool">bool</option><option value="array">array</option></select></td>
            <td class="p-2"><input name="mappings[${index}][default_value]" class="w-40 rounded border-gray-300" placeholder="не задано"></td>
            <td class="p-2 text-center"><input type="checkbox" name="mappings[${index}][is_required]" value="1"></td>
            <td class="p-2 whitespace-nowrap"><button type="button" class="move-up rounded border px-2 py-1">↑</button> <button type="button" class="move-down rounded border px-2 py-1">↓</button></td>
            <td class="p-2"><button type="button" class="remove-mapping rounded bg-red-600 px-2 py-1 text-white">Удалить</button></td>`;
        rows.appendChild(row);
        row.querySelector('[name$="[source_path]"]').value = rule.source_path ?? '';
        row.querySelector('[name$="[target_path]"]').value = rule.target_path ?? '';
        row.querySelector('[name$="[cast_type]"]').value = rule.cast_type ?? 'raw';
        row.querySelector('[name$="[default_value]"]').value = rule.default_value ?? '';
        row.querySelector('[type=checkbox]').checked = !!rule.is_required;
    }

    addButton.addEventListener('click', () => addRow());
    rows.addEventListener('click', (event) => {
        const row = event.target.closest('.mapping-row');
        if (!row) return;
        if (event.target.closest('.remove-mapping')) row.remove();
        if (event.target.closest('.move-up') && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
        if (event.target.closest('.move-down') && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
        renumberRows();
    });

    function currentMappings() {
        return [...rows.querySelectorAll('.mapping-row')].map((row) => ({
            source_path: row.querySelector('[name$="[source_path]"]').value,
            target_path: row.querySelector('[name$="[target_path]"]').value,
            cast_type: row.querySelector('[name$="[cast_type]"]').value,
            default_value: row.querySelector('[name$="[default_value]"]').value || null,
            is_required: row.querySelector('[type=checkbox]').checked
        }));
    }

    document.getElementById('preview-mapping').addEventListener('click', async () => {
        const error = document.getElementById('preview-error');
        const output = document.getElementById('preview-output');
        error.textContent = '';
        try {
            const response = await fetch(@json(route('dashboard.endpoints.mappings.preview', $endpoint)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    payload: document.getElementById('sample-payload').value,
                    mappings: currentMappings()
                })
            });
            const result = await response.json();
            if (!response.ok) {
                const messages = Array.isArray(result.errors)
                    ? result.errors
                    : Object.values(result.errors || {}).flat();
                throw new Error(messages.join('\n') || result.message);
            }
            output.textContent = JSON.stringify(result.payload, null, 2);
        } catch (exception) {
            error.textContent = exception.message;
        }
    });
</script>
@endsection
