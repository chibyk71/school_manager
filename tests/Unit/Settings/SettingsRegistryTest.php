<?php

/**
 * Phase 1 — SettingsRegistry unit coverage.
 */

use App\Settings\Definitions\AuthenticationSettings;
use App\Support\Settings\Exceptions\InvalidSettingDefinitionException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingGroupDefinition;
use App\Support\Settings\SettingsRegistry;

test('registers authentication pilot definitions and retrieves them', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    $life = $registry->get('authentication.reset_password_token_life');
    expect($life)->toBeInstanceOf(SettingDefinition::class)
        ->and($life->type)->toBe('integer')
        ->and($life->default)->toBe(60)
        ->and($life->intendedScope)->toBe('school');

    $allow = $registry->get('authentication.allow_password_reset');
    expect($allow->type)->toBe('boolean')
        ->and($allow->default)->toBeTrue();
});

test('forGroup returns definitions for authentication', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    $group = $registry->forGroup('authentication');
    expect($group)->toHaveCount(2)
        ->and($group)->toHaveKey('authentication.reset_password_token_life')
        ->and($group)->toHaveKey('authentication.allow_password_reset');
});

test('allGrouped indexes definitions by group key', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    $all = $registry->allGrouped();
    expect($all)->toHaveKey('authentication')
        ->and($all['authentication'])->toHaveCount(2);
});

test('get throws UnknownSettingException for unknown key', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    expect(fn () => $registry->get('authentication.unknown'))
        ->toThrow(UnknownSettingException::class);
});

test('find returns null for unknown key', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    expect($registry->find('authentication.unknown'))->toBeNull();
});

test('duplicate group key fails explicitly', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    expect(fn () => AuthenticationSettings::register($registry))
        ->toThrow(InvalidSettingDefinitionException::class);
});

test('duplicate setting key across groups fails', function () {
    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);

    expect(fn () => $registry->registerGroup(new SettingGroupDefinition(
        'other',
        'Other',
        [
            'authentication.reset_password_token_life' => new SettingDefinition(
                key: 'authentication.reset_password_token_life',
                groupKey: 'other',
                groupLabel: 'Other',
                type: 'integer',
                default: 1,
                label: 'Dup',
            ),
        ],
    )))->toThrow(InvalidSettingDefinitionException::class);
});

test('default incompatible with type fails', function () {
    $registry = new SettingsRegistry;

    expect(fn () => $registry->registerGroup(new SettingGroupDefinition(
        'bad',
        'Bad',
        [
            'bad.key' => new SettingDefinition(
                key: 'bad.key',
                groupKey: 'bad',
                groupLabel: 'Bad',
                type: 'integer',
                default: 'sixty',
                label: 'Bad default',
            ),
        ],
    )))->toThrow(InvalidSettingDefinitionException::class);
});

test('unsupported type fails', function () {
    $registry = new SettingsRegistry;

    expect(fn () => $registry->registerGroup(new SettingGroupDefinition(
        'bad',
        'Bad',
        [
            'bad.key' => new SettingDefinition(
                key: 'bad.key',
                groupKey: 'bad',
                groupLabel: 'Bad',
                type: 'float',
                default: 1.5,
                label: 'Float',
            ),
        ],
    )))->toThrow(InvalidSettingDefinitionException::class);
});

test('empty label fails', function () {
    $registry = new SettingsRegistry;

    expect(fn () => $registry->registerGroup(new SettingGroupDefinition(
        'bad',
        'Bad',
        [
            'bad.key' => new SettingDefinition(
                key: 'bad.key',
                groupKey: 'bad',
                groupLabel: 'Bad',
                type: 'integer',
                default: 1,
                label: '',
            ),
        ],
    )))->toThrow(InvalidSettingDefinitionException::class);
});
