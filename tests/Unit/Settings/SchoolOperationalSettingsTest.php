<?php

/**
 * School Management Phase 2 — SchoolOperationalSettings registration,
 * defaults, validation helpers, and school-scope isolation.
 */

use App\Settings\Definitions\SchoolOperationalSettings;
use App\Support\Settings\Exceptions\InvalidSettingValueException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('school operational definitions are registered with intended school scope', function () {
    $registry = app(SettingsRegistry::class);

    foreach (SchoolOperationalSettings::fieldKeyMap() as $fullKey) {
        $def = $registry->get($fullKey);
        expect($def->intendedScope)->toBe('school')
            ->and($def->type)->toBe('string');
    }

    expect($registry->forGroup(SchoolOperationalSettings::GROUP))->toHaveCount(6);
});

test('defaults resolve when no school override exists', function () {
    $resolver = app(SettingsResolver::class);
    $scope = SettingsScope::school('00000000-0000-0000-0000-000000000001');

    expect($resolver->get(SchoolOperationalSettings::TIMEZONE, $scope))
        ->toBe((string) config('app.timezone', 'UTC'));
    expect($resolver->get(SchoolOperationalSettings::DATE_FORMAT, $scope))->toBe('d/m/Y');
    expect($resolver->get(SchoolOperationalSettings::TIME_FORMAT, $scope))->toBe('H:i');
    expect($resolver->get(SchoolOperationalSettings::WEEK_START, $scope))->toBe('monday');
    expect($resolver->get(SchoolOperationalSettings::LOCALE, $scope))
        ->toBe((string) config('app.locale', 'en'));
    expect($resolver->get(SchoolOperationalSettings::DEFAULT_CURRENCY, $scope))->toBe('NGN');
});

test('school overrides are isolated between schools', function () {
    $resolver = app(SettingsResolver::class);
    $scopeA = SettingsScope::school('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
    $scopeB = SettingsScope::school('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

    $resolver->set(SchoolOperationalSettings::TIMEZONE, 'Africa/Lagos', $scopeA);
    $resolver->set(SchoolOperationalSettings::TIMEZONE, 'Europe/London', $scopeB);

    expect($resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeA))->toBe('Africa/Lagos');
    expect($resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeB))->toBe('Europe/London');
    $scopeC = SettingsScope::school('cccccccc-cccc-cccc-cccc-cccccccccccc');
    expect($resolver->get(SchoolOperationalSettings::TIMEZONE, $scopeC))
        ->toBe((string) config('app.timezone', 'UTC'));
});

test('unknown operational key throws', function () {
    $resolver = app(SettingsResolver::class);

    expect(fn () => $resolver->get('school_operational.not_a_key', SettingsScope::school('x')))
        ->toThrow(UnknownSettingException::class);
});

test('non-string values are rejected by resolver', function () {
    $resolver = app(SettingsResolver::class);
    $scope = SettingsScope::school('dddddddd-dddd-dddd-dddd-dddddddddddd');

    expect(fn () => $resolver->set(SchoolOperationalSettings::LOCALE, 123, $scope))
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
