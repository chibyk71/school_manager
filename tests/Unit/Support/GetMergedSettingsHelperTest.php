<?php

namespace Tests\Unit\Support;

use App\Models\School;
use App\Support\Settings\Exceptions\InvalidStoredSettingValueException;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Production helper path: getMergedSettings must exist without test-only stubs.
 * SettingsRegistry / SettingsResolver are final — use lightweight doubles, not Mockery class mocks.
 */
class GetMergedSettingsHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_helper_function_is_defined_in_production_helpers(): void
    {
        $this->assertTrue(function_exists('getMergedSettings'));
    }

    public function test_unregistered_group_returns_empty_array(): void
    {
        $result = getMergedSettings('maintenance', null);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function test_no_school_context_does_not_throw(): void
    {
        $result = getMergedSettings('maintenance');
        $this->assertIsArray($result);
    }

    public function test_school_owner_resolves_with_school_scope_and_null_owner_uses_global(): void
    {
        if (! Schema::hasTable('schools')) {
            $this->markTestSkipped('schools table required');
        }

        $school = School::query()->create([
            'name' => 'Settings Scope School',
            'is_active' => false,
        ]);
        $this->assertNotEmpty($school->getKey());

        $definition = new SettingDefinition(
            key: 'compat.flag',
            groupKey: 'compat',
            groupLabel: 'Compat',
            type: 'boolean',
            default: false,
            label: 'Flag',
        );

        $registry = new class($definition)
        {
            public function __construct(private SettingDefinition $definition) {}

            public function forGroup(string $group): array
            {
                return $group === 'compat'
                    ? ['compat.flag' => $this->definition]
                    : [];
            }
        };

        $resolver = new class($school->getKey())
        {
            /** @var list<array{0: string, 1: string}> */
            public array $calls = [];

            public function __construct(private string|int $schoolId) {}

            public function get(string $key, string $scope): mixed
            {
                $this->calls[] = [$key, $scope];

                if ($key === 'compat.flag' && $scope === 'school:'.$this->schoolId) {
                    return true;
                }

                if ($key === 'compat.flag' && $scope === 'global') {
                    return false;
                }

                throw new \RuntimeException("Unexpected get({$key}, {$scope})");
            }
        };

        $this->app->instance(SettingsRegistry::class, $registry);
        $this->app->instance(SettingsResolver::class, $resolver);

        $schoolScoped = getMergedSettings('compat', $school);
        $this->assertSame(['flag' => true], $schoolScoped);
        $this->assertSame(
            [['compat.flag', 'school:'.$school->getKey()]],
            $resolver->calls
        );

        $resolver->calls = [];
        $global = getMergedSettings('compat', null);
        $this->assertSame(['flag' => false], $global);
        $this->assertSame(
            [['compat.flag', 'global']],
            $resolver->calls
        );
    }

    public function test_resolver_invalid_stored_value_is_not_swallowed(): void
    {
        $definition = new SettingDefinition(
            key: 'compat.flag',
            groupKey: 'compat',
            groupLabel: 'Compat',
            type: 'boolean',
            default: false,
            label: 'Flag',
        );

        $registry = new class($definition)
        {
            public function __construct(private SettingDefinition $definition) {}

            public function forGroup(string $group): array
            {
                return $group === 'compat'
                    ? ['compat.flag' => $this->definition]
                    : [];
            }
        };

        $resolver = new class
        {
            public function get(string $key, string $scope): mixed
            {
                throw new InvalidStoredSettingValueException('corrupt stored value');
            }
        };

        $this->app->instance(SettingsRegistry::class, $registry);
        $this->app->instance(SettingsResolver::class, $resolver);

        $this->expectException(InvalidStoredSettingValueException::class);
        getMergedSettings('compat', null);
    }

    public function test_maintenance_middleware_path_survives_without_registered_group(): void
    {
        $this->assertTrue(function_exists('getMergedSettings'));
        $settings = getMergedSettings('maintenance', null);
        $this->assertSame('disabled', $settings['mode'] ?? 'disabled');
    }
}
