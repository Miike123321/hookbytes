<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EnvironmentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class EnvironmentSettingsController extends Controller
{
    public function __construct(private readonly EnvironmentSettingsService $environment) {}

    public function index(): View
    {
        try {
            $groups = $this->environment->formGroups();
            $activeFile = $this->environment->activeFileName();
            $fileWritable = $this->environment->isWritable();
            $fileError = null;
        } catch (RuntimeException $exception) {
            $groups = [];
            $activeFile = basename(app()->environmentFilePath());
            $fileWritable = false;
            $fileError = $exception->getMessage();
        }

        return view('admin.environment.index', compact('groups', 'activeFile', 'fileWritable', 'fileError'));
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = $this->environment->editableFields();
        $settings = $request->input('settings', []);

        if (! is_array($settings)) {
            return back()->withErrors(['settings' => 'Переданы настройки в неверном формате.']);
        }

        if (array_diff(array_keys($settings), array_keys($fields)) !== []) {
            return back()->withErrors(['settings' => 'Передан параметр, который нельзя изменять из панели.']);
        }

        $rules = ['settings' => ['required', 'array']];
        foreach ($fields as $key => $field) {
            $path = "settings.{$key}";
            $rules[$path] = match ($field['type']) {
                'boolean' => ['required', 'boolean'],
                'number' => ['nullable', 'integer', ...$this->numericRules($field)],
                'select' => ['nullable', Rule::in($field['options'])],
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:2048'],
                default => ['nullable', 'string', 'max:4096', 'regex:/\A[^\r\n]*\z/'],
            };
        }

        $clearSecretRules = ['clear_secrets' => ['nullable', 'array']];
        foreach ($fields as $key => $field) {
            if ($field['secret']) {
                $clearSecretRules["clear_secrets.{$key}"] = ['nullable', 'boolean'];
            }
        }

        $validator = Validator::make(
            $request->all(),
            [...$rules, ...$clearSecretRules],
            [
                'required' => 'Поле :attribute обязательно для заполнения.',
                'boolean' => 'Поле :attribute должно иметь логическое значение.',
                'integer' => 'Поле :attribute должно быть целым числом.',
                'min' => 'Значение поля :attribute должно быть не меньше :min.',
                'max' => 'Значение поля :attribute не должно превышать :max.',
                'in' => 'Выберите допустимое значение для поля :attribute.',
                'email' => 'Укажите корректный адрес электронной почты.',
                'url' => 'Укажите корректный URL.',
                'regex' => 'Переносы строк в параметрах окружения запрещены.',
                'settings.*.string' => 'Значение должно быть строкой.',
            ]
        );
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($this->safeInput($request, $fields));
        }
        $validated = $validator->validated();

        $resolvedEnvironment = $validated['settings']['APP_ENV'] ?? config('app.env');
        if ($resolvedEnvironment === 'production' && $request->boolean('settings.APP_DEBUG')) {
            return back()
                ->withInput($this->safeInput($request, $fields))
                ->withErrors(['settings.APP_DEBUG' => 'Нельзя включить APP_DEBUG для production-среды.']);
        }

        $clearSecrets = [];
        foreach (array_keys($fields) as $key) {
            if ($fields[$key]['secret'] && $request->boolean("clear_secrets.{$key}")) {
                $clearSecrets[] = $key;
            }
        }

        try {
            $updated = $this->environment->save($validated['settings'], $clearSecrets);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($this->safeInput($request, $fields))
                ->withErrors(['environment' => $exception->getMessage()]);
        }

        if (! app()->environment('testing')) {
            Artisan::call('config:clear');
        }

        return redirect()->route('admin.environment.index')
            ->with('success', 'Настройки записаны в активный файл окружения.')
            ->with('updatedKeys', $updated)
            ->with('warning', 'Изменения конфигурации применятся новым запросам. Перезапустите PHP-FPM, Docker-сервисы и queue workers для полного применения; Docker Compose-переменные изменяйте на хосте.');
    }

    private function numericRules(array $field): array
    {
        $rules = [];
        if ($field['min'] !== null) {
            $rules[] = 'min:'.$field['min'];
        }
        if ($field['max'] !== null) {
            $rules[] = 'max:'.$field['max'];
        }

        return $rules;
    }

    private function safeInput(Request $request, array $fields): array
    {
        $input = $request->except(['_token', '_method']);
        foreach ($fields as $key => $field) {
            if ($field['secret'] && isset($input['settings']) && is_array($input['settings'])) {
                unset($input['settings'][$key]);
            }
        }

        return $input;
    }
}
