<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EnvironmentSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private EnvironmentSettingsService $environment;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.owner_email' => 'owner@example.test']);
        $this->path = tempnam(sys_get_temp_dir(), 'hookbytes-admin-env-');
        file_put_contents($this->path, "APP_NAME=HookBytes\nAPP_ENV=testing\nDB_PASSWORD=existing-secret\n");
        $this->environment = new EnvironmentSettingsService($this->path);
        $this->app->instance(EnvironmentSettingsService::class, $this->environment);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_only_owner_can_open_environment_settings_and_secrets_are_masked(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->get(route('admin.environment.index'))
            ->assertForbidden();

        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.environment.index'))
            ->assertOk()
            ->assertSee('Подключения и настройки окружения')
            ->assertSee('DB_PASSWORD')
            ->assertSee('•••••••• (значение задано)')
            ->assertDontSee('existing-secret');
    }

    public function test_owner_can_update_environment_settings_without_flashing_or_exposing_secrets(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'email_verified_at' => now(),
        ]);
        $settings = $this->validSettings();
        $settings['APP_NAME'] = 'Updated HookBytes';
        $settings['DB_PASSWORD'] = 'new-secret-value';

        $this->actingAs($owner)
            ->put(route('admin.environment.update'), ['settings' => $settings])
            ->assertRedirect(route('admin.environment.index'));

        $values = \Dotenv\Dotenv::parse(file_get_contents($this->path));
        self::assertSame('Updated HookBytes', $values['APP_NAME']);
        self::assertSame('new-secret-value', $values['DB_PASSWORD']);
        self::assertStringNotContainsString('new-secret-value', serialize(session('_flash.old', [])));
    }

    public function test_production_debug_is_rejected_without_flashing_secret_input(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'email_verified_at' => now(),
        ]);
        $settings = $this->validSettings();
        $settings['APP_ENV'] = 'production';
        $settings['APP_DEBUG'] = '1';
        $settings['DB_PASSWORD'] = 'do-not-flash-this-secret';

        $this->actingAs($owner)
            ->from(route('admin.environment.index'))
            ->put(route('admin.environment.update'), ['settings' => $settings])
            ->assertRedirect(route('admin.environment.index'))
            ->assertSessionHasErrors('settings.APP_DEBUG');

        $oldInput = session('_old_input.settings', []);
        self::assertArrayNotHasKey('DB_PASSWORD', $oldInput);
        self::assertStringNotContainsString('do-not-flash-this-secret', serialize($oldInput));
        self::assertSame('HookBytes', \Dotenv\Dotenv::parse(file_get_contents($this->path))['APP_NAME']);
    }

    private function validSettings(): array
    {
        $settings = [];
        foreach ($this->environment->editableFields() as $key => $field) {
            $settings[$key] = match ($field['type']) {
                'boolean' => '0',
                'number' => (string) ($field['default'] ?? $field['min'] ?? 1),
                'select' => (string) ($field['default'] ?? $field['options'][0] ?? ''),
                default => (string) ($field['default'] ?? ''),
            };
        }

        $settings['APP_ENV'] = 'testing';
        $settings['APP_DEBUG'] = '0';

        return $settings;
    }
}
