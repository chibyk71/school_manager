<?php

/**
 * Phase 1 — registration lifecycle / container singleton coverage.
 */

use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;

test('container resolves registry singleton with authentication definitions', function () {
    $registry = app(SettingsRegistry::class);

    expect($registry->get('authentication.reset_password_token_life')->default)->toBe(60)
        ->and($registry->get('authentication.allow_password_reset')->default)->toBeTrue()
        ->and($registry->isSealed())->toBeTrue();
});

test('container registry includes school operational definitions', function () {
    $registry = app(SettingsRegistry::class);

    expect($registry->get('school_operational.timezone')->intendedScope)->toBe('school')
        ->and($registry->forGroup('school_operational'))->toHaveCount(6);
});

test('repeated container resolution does not duplicate registration', function () {
    $a = app(SettingsRegistry::class);
    $b = app(SettingsRegistry::class);

    expect($a)->toBe($b)
        ->and($a->forGroup('authentication'))->toHaveCount(2);
});

test('resolver is resolved from container against the same registry', function () {
    $resolver = app(SettingsResolver::class);
    $registry = app(SettingsRegistry::class);

    expect($resolver)->toBeInstanceOf(SettingsResolver::class);

    // Unknown key still fails through container-bound resolver
    expect(fn () => $resolver->get('nope', 'school:1'))
        ->toThrow(\App\Support\Settings\Exceptions\UnknownSettingException::class);
});
