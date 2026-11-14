@extends('layouts.master')

@section('title', 'Настройки окружения')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.operations.index') }}" class="text-sm text-indigo-600 hover:underline">← К панели операций</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">Подключения и настройки окружения</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Активный файл: <code>{{ $activeFile }}</code></p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $fileWritable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
            {{ $fileWritable ? 'Файл доступен для записи' : 'Файл недоступен для записи' }}
        </span>
    </div>

    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
        Панель изменяет только активный <code>.env</code>. Шаблоны <code>.env.example</code> и <code>.env.docker</code> не изменяются.
        В Docker переменные из Compose могут перекрывать файл; порты и настройки сборки доступны только для просмотра.
        После изменения подключений перезапустите PHP-FPM, Docker-сервисы и queue workers. Не включайте debug на публичном production-сайте.
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($fileError)
        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">{{ $fileError }}</div>
    @endif

    @if ($groups === [])
        <p class="rounded-lg bg-white p-5 text-sm text-gray-700 shadow dark:bg-gray-800 dark:text-gray-300">
            Файл окружения не прочитан, поэтому редактирование отключено. Проверьте конфигурацию окружения контейнера и доступ приложения к активному файлу.
        </p>
    @else
    <form method="POST" action="{{ route('admin.environment.update') }}" class="space-y-5" autocomplete="off">
        @csrf
        @method('PUT')

        @foreach ($groups as $group)
            <details class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800" @if ($loop->first) open @endif>
                <summary class="cursor-pointer border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $group['title'] }}</span>
                    <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ $group['description'] }}</span>
                </summary>
                <div class="grid gap-5 p-5 md:grid-cols-2">
                    @foreach ($group['fields'] as $field)
                        <div class="min-w-0">
                            <label for="setting-{{ $field['key'] }}" class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                {{ $field['label'] }}
                                <span class="ml-1 font-mono text-xs text-gray-500">{{ $field['key'] }}</span>
                            </label>

                            @if (! $field['editable'])
                                <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @if ($field['secret'])
                                        {{ $field['configured'] ? '•••••••• (значение задано)' : 'Не задано' }}
                                    @else
                                        {{ $field['value'] !== '' ? $field['value'] : 'Не задано' }}
                                    @endif
                                    <span class="ml-2 text-xs text-gray-500">Только просмотр</span>
                                </div>
                            @elseif ($field['secret'])
                                <input id="setting-{{ $field['key'] }}" type="password" name="settings[{{ $field['key'] }}]" value="" autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="{{ $field['configured'] ? 'Секрет задан; пустое поле сохранит его' : 'Введите значение' }}">
                                @if ($field['configured'])
                                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-red-700 dark:text-red-300">
                                        <input type="checkbox" name="clear_secrets[{{ $field['key'] }}]" value="1">
                                        Удалить сохраненный секрет
                                    </label>
                                @endif
                            @elseif ($field['type'] === 'boolean')
                                <input type="hidden" name="settings[{{ $field['key'] }}]" value="0">
                                <label class="mt-2 flex items-center gap-2">
                                    <input id="setting-{{ $field['key'] }}" type="checkbox" name="settings[{{ $field['key'] }}]" value="1" @checked(filter_var(old('settings.'.$field['key'], $field['value']), FILTER_VALIDATE_BOOLEAN)) class="rounded border-gray-300 text-indigo-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Включено</span>
                                </label>
                            @elseif ($field['type'] === 'select')
                                <select id="setting-{{ $field['key'] }}" name="settings[{{ $field['key'] }}]" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    @foreach ($field['options'] as $option)
                                        <option value="{{ $option }}" @selected((string) old('settings.'.$field['key'], $field['value']) === (string) $option)>
                                            {{ $option === '' ? 'Пусто / значение по умолчанию' : $option }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    id="setting-{{ $field['key'] }}"
                                    type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                    name="settings[{{ $field['key'] }}]"
                                    value="{{ old('settings.'.$field['key'], $field['value']) }}"
                                    @if ($field['min'] !== null) min="{{ $field['min'] }}" @endif
                                    @if ($field['max'] !== null) max="{{ $field['max'] }}" @endif
                                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                >
                            @endif

                            @if ($field['help'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $field['help'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-5 shadow dark:bg-gray-800">
            <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                Секреты не отображаются и не попадают в старые значения формы. Пустое поле оставляет секрет без изменений; для удаления используйте отдельный флажок.
            </p>
            <button type="submit" @disabled(! $fileWritable) class="rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                Сохранить настройки окружения
            </button>
        </div>
    </form>
    @endif
</div>
@endsection
