<?php

namespace Tests\Unit;

use App\Services\EnvironmentSettingsService;
use Dotenv\Dotenv;
use Tests\TestCase;

class EnvironmentSettingsServiceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'hookbytes-env-');
        file_put_contents($this->path, implode("\n", [
            '# Keep this comment',
            'APP_NAME=HookBytes',
            'DB_PASSWORD="existing-secret"',
            'DB_PASSWORD="duplicate-secret"',
            'CUSTOM_SETTING=keep-me',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_updates_allowlisted_keys_and_removes_duplicate_entries(): void
    {
        $service = new EnvironmentSettingsService($this->path);
        $updated = $service->save([
            'APP_NAME' => 'HookBytes $STAGE #1',
            'DB_PASSWORD' => 'new-secret',
            'APP_DEBUG' => 'true',
        ]);
        $contents = file_get_contents($this->path);
        $values = Dotenv::parse($contents);

        self::assertSame(['APP_NAME', 'DB_PASSWORD', 'APP_DEBUG'], $updated);
        self::assertSame('HookBytes $STAGE #1', $values['APP_NAME']);
        self::assertSame('new-secret', $values['DB_PASSWORD']);
        self::assertSame('true', $values['APP_DEBUG']);
        self::assertSame('keep-me', $values['CUSTOM_SETTING']);
        self::assertSame(1, preg_match_all('/^DB_PASSWORD=/m', $contents));
        self::assertStringContainsString('# Keep this comment', $contents);
    }

    public function test_null_secret_input_preserves_the_active_value(): void
    {
        $service = new EnvironmentSettingsService($this->path);
        $service->save([
            'APP_NAME' => 'Still HookBytes',
            'DB_PASSWORD' => null,
        ]);

        self::assertSame('duplicate-secret', Dotenv::parse(file_get_contents($this->path))['DB_PASSWORD']);
    }

    public function test_secret_values_are_never_included_in_view_data_and_can_be_cleared_explicitly(): void
    {
        $service = new EnvironmentSettingsService($this->path);
        $groups = $service->formGroups();
        $fields = collect($groups)->flatMap(fn (array $group) => $group['fields']);
        $passwordField = $fields->firstWhere('key', 'DB_PASSWORD');

        self::assertTrue($passwordField['configured']);
        self::assertSame('', $passwordField['value']);
        self::assertStringNotContainsString('existing-secret', serialize($groups));

        $service->save(['DB_PASSWORD' => ''], ['DB_PASSWORD']);

        self::assertSame('', Dotenv::parse(file_get_contents($this->path))['DB_PASSWORD']);
    }

    public function test_every_variable_in_environment_templates_is_available_in_the_admin_catalog(): void
    {
        $service = new EnvironmentSettingsService($this->path);
        $catalog = collect($service->groups())->flatMap(fn (array $group) => $group['fields'])
            ->pluck('key')
            ->all();

        foreach (['.env.example', '.env.docker'] as $template) {
            $contents = file_get_contents(base_path($template));
            preg_match_all('/^\s*#?\s*([A-Z][A-Z0-9_]*)\s*=/m', $contents, $matches);

            foreach (array_unique($matches[1]) as $key) {
                self::assertContains($key, $catalog, "Missing environment setting {$key} from {$template}.");
            }
        }
    }
}
