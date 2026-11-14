@extends('layouts.master')

@section('title', 'Application Logs - HookBytes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Application logs</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Последние 200 строк, максимум 64 КБ из выбранного файла.</p>
        </div>
        <a href="{{ route('admin.operations.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800 hover:bg-gray-300">Назад к панели</a>
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <label for="file" class="text-sm font-medium text-gray-700 dark:text-gray-200">Файл лога</label>
        <select id="file" name="file" class="rounded-md border-gray-300 text-sm">
            @foreach ($files as $file)
                <option value="{{ $file->getFilename() }}" @selected($selectedFile?->getFilename() === $file->getFilename())>{{ $file->getFilename() }}</option>
            @endforeach
        </select>
        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Показать</button>
    </form>
    <div class="overflow-x-auto rounded-lg bg-gray-950 p-4 shadow">
        @if ($selectedFile)
            <pre class="whitespace-pre-wrap break-words text-xs leading-5 text-green-200">{{ implode("\n", $lines) }}</pre>
        @else
            <p class="text-sm text-gray-300">Файлов логов в storage/logs пока нет. Если LOG_CHANNEL настроен на stderr, логи доступны через журналы контейнера/сервиса.</p>
        @endif
    </div>
</div>
@endsection
