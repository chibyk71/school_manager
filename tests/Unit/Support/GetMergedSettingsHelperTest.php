<?php

namespace Tests\Unit\Support;

use App\Models\School;
use App\Support\Settings\Exceptions\InvalidStoredSettingValueException;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Production helper path: getMergedSettings must exist without test-only stubs.
 */
class GetMergedSettingsHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

        $registry = Mockery::mock(SettingsRegistry::class);
        $registry->shouldReceive('forGroup')
            ->with('compat')
            ->andReturn(['compat.flag' => $definition]);

        $resolver = Mockery::mock(SettingsResolver::class);
        $resolver->shouldReceive('get')
            ->once()
            ->with('compat.flag', 'school:'.$school->getKey())
            ->andReturn(true);
        $resolver->shouldReceive('get')
            ->once()
            ->with('compat.flag', 'global')
            ->andReturn(false);

        $this->app->instance(SettingsRegistry::class, $registry);
        $this->app->instance(SettingsResolver::class, $resolver);

        $schoolScoped = getMergedSettings('compat', $school);
        $this->assertSame(['flag' => true], $schoolScoped);

        $global = getMergedSettings('compat', null);
        $this->assertSame(['flag' => false], $global);
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

        $registry = Mockery::mock(SettingsRegistry::class);
        $registry->shouldReceive('forGroup')
            ->with('compat')
            ->andReturn(['compat.flag' => $definition]);

        $resolver = Mockery::mock(SettingsResolver::class);
        $resolver->shouldReceive('get')
            ->once()
            ->with('compat.flag', 'global')
            ->andThrow(new InvalidStoredSettingValueException('corrupt stored value'));

        $this->app->instance(SettingsRegistry::class, $registry);
        $this->app->instance(SettingsResolver::class, $resolver);

        $this->expectException(InvalidStoredSettingValueException::class);
        getMergedSettings('compat', null);
    }

    public function test_maintenance_middleware_path_survives_without_registered_group(): void
    {
        $this->assertTrue(function_exists('getMergedSettings'));
        $settings = getMergedSettings('maintenance', null);
        // CheckMaintenanceMode treats missing/empty as disabled.
        $this->assertSame('disabled', $settings['mode'] ?? 'disabled');
    }
}
