<?php

/**
 * School Management Phase 2 — SchoolOperationalSettings registration,
 * defaults, validation helpers, and school-scope isolation.
 *
 * Uses an ephemeral OI settings table (same practice as SettingsResolverTest)
 * so resolver get/set does not require full migrate.
 */

use App\Settings\Definitions\SchoolOperationalSettings;
use App\Support\Settings\Exceptions\InvalidSettingValueException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config([
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'oi-laravel-settings.table' => 'settings',
        'oi-laravel-settings.cache.enabled' => true,
        'oi-laravel-settings.cache.prefix' => 'oi-settings-school-ops-test',
        'oi-laravel-settings.cache.ttl' => null,
        'oi-laravel-settings.scope_resolver' => null,
        'oi-laravel-settings.default_scope' => null,
    ]);

    Schema::dropIfExists('settings');
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('scope')->nullable()->index();
        $table->string('key');
        $table->string('label');
        $table->string('type')->default('string');
        $table->text('value')->nullable();
        $table->timestamps();
        $table->unique(['scope', 'key']);
    });

    Cache::flush();

    $registry = new SettingsRegistry;
    SchoolOperationalSettings::register($registry);
    $registry->seal();

    $this->registry = $registry;
    $this->resolver = new SettingsResolver($registry);
});

afterEach(function () {
    Schema::dropIfExists('settings');
    Cache::flush();
});

test('school operational definitions register with intended school scope', function () {
    foreach (SchoolOperationalSettings::fieldKeyMap() as $fullKey) {
        $def = $this->registry->get($fullKey);
        expect($def->intendedScope)->toBe('school')
            ->and($def->type)->toBe('string');
    }

    expect($this->registry->forGroup(SchoolOperationalSettings::GROUP))->toHaveCount(6);
});

test('container registry includes school operational definitions when AppServiceProvider registered them', function () {
    $registry = app(SettingsRegistry::class);

    expect($registry->find(SchoolOperationalSettings::TIMEZONE))->not->toBeNull(
        'SchoolOperationalSettings must be registered in AppServiceProvider before seal()'
    );

    expect($registry->get(SchoolOperationalSettings::TIMEZONE)->intendedScope)->toBe('school')
        ->and($registry->forGroup(SchoolOperationalSettings::GROUP))->toHaveCount(6);
});

test('defaults resolve when no school override exists', function () {
    $scope = SettingsScope::school('00000000-0000-0000-0000-000000000001');

    expect($this->resolver->get(SchoolOperationalSettings::TIMEZONE, $scope))
        ->toBe((string) config('app.timezone', 'UTC'));
    expect($this->resolver->get(SchoolOperationalSettings::DATE_FORMAT, $scope))->toBe('d/m/Y');
    expect($this->resolver->get(SchoolOperationalSettings::TIME_FORMAT, $scope))->toBe('H:i');
    expect($this->resolver->get(SchoolOperationalSettings::WEEK_START, $scope))->toBe('monday');
    expect($this->resolver->get(SchoolOperationalSettings::LOCALE, $scope))
        ->toBe((string) config('app.locale', 'en'));
    expect($this->resolver->get(SchoolOperationalSettings::DEFAULT_CURRENCY, $scope))->toBe('NGN');
});

test('school overrides are isolated between schools', function () {
    $scopeA = SettingsScope::school('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
    $scopeB = SettingsScope::school('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

    $this->resolver->set(SchoolOperationalSettings::TIMEZONE, 'Africa/Lagos', $scopeA);
    $this->resolver->set(SchoolOperationalSettings::TIMEZONE, 'Europe/London', $scopeB);

    expect($this->resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeA))->toBe('Africa/Lagos');
    expect($this->resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeB))->toBe('Europe/London');

    $scopeC = SettingsScope::school('cccccccc-cccc-cccc-cccc-cccccccccccc');
    expect($this->resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeC))
        ->toBe((string) config('app.timezone', 'UTC'));
});

test('unknown operational key throws', function () {
    expect(fn () => $this->resolver->get('school_operational.not_a_key', SettingsScope::school('x')))
        ->toThrow(UnknownSettingException::class);
});

test('non-string values are rejected by resolver', function () {
    $scope = SettingsScope::school('dddddddd-dddd-dddd-dddd-dddddddddddd');

    expect(fn () => $this->resolver->set(SchoolOperationalSettings::LOCALE, 123, $scope))
        ->toThrow(InvalidSettingValueException::class);
});

test('validation rules reject invalid timezone locale currency week_start and formats', function () {
    $rules = SchoolOperationalSettings::validationRules();

    $invalid = Validator::make([
        'timezone' => 'Not/A_Real_Zone',
        'date_format' => 'nonsense',
        'time_format' => 'xx',
        'week_start' => 'wednesday',
        'locale' => 'xx_INVALID',
        'default_currency' => 'XXXX',
    ], $rules);

    expect($invalid->fails())->toBeTrue();
    expect($invalid->errors()->has('timezone'))->toBeTrue();
    expect($invalid->errors()->has('date_format'))->toBeTrue();
    expect($invalid->errors()->has('time_format'))->toBeTrue();
    expect($invalid->errors()->has('week_start'))->toBeTrue();
    expect($invalid->errors()->has('locale'))->toBeTrue();
    expect($invalid->errors()->has('default_currency'))->toBeTrue();

    $valid = Validator::make([
        'timezone' => 'Africa/Lagos',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'week_start' => 'monday',
        'locale' => 'en',
        'default_currency' => 'NGN',
    ], $rules);

    expect($valid->fails())->toBeFalse();
});
