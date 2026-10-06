<?php

/**
 * Phase 1 — OI Laravel Settings infrastructure coverage.
 *
 * Does not use full RefreshDatabase (SQLite migrate broken by unrelated devices
 * migration). Creates the OI settings schema ephemerally, matching Documentation
 * and Permission HTTP test practice.
 */

use App\Support\Settings\SettingsScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use OiLab\OiLaravelSettings\Facades\Settings;
use OiLab\OiLaravelSettings\Models\Setting;
use OiLab\OiLaravelSettings\SettingsManager;

beforeEach(function () {
    config([
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'oi-laravel-settings.table' => 'settings',
        'oi-laravel-settings.cache.enabled' => true,
        'oi-laravel-settings.cache.prefix' => 'oi-settings-test',
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
});

afterEach(function () {
    Schema::dropIfExists('settings');
    Cache::flush();
});

test('persists and retrieves a string setting for an explicit scope', function () {
    $scope = SettingsScope::school('school-a');

    Settings::set('demo.string', 'hello', type: 'string', label: 'Demo string', scope: $scope);

    expect(Settings::get('demo.string', null, $scope))->toBe('hello')
        ->and(Settings::has('demo.string', $scope))->toBeTrue();
});

test('updates a stored value and returns the new value', function () {
    $scope = SettingsScope::user('user-1');

    Settings::set('ui.theme', 'light', type: 'string', label: 'Theme', scope: $scope);
    Settings::set('ui.theme', 'dark', type: 'string', label: 'Theme', scope: $scope);

    expect(Settings::get('ui.theme', null, $scope))->toBe('dark');
});

test('returns default when no explicit override exists', function () {
    $scope = SettingsScope::school('school-empty');

    expect(Settings::get('missing.key', 15, $scope))->toBe(15)
        ->and(Settings::has('missing.key', $scope))->toBeFalse();
});

test('typed values survive write persist read', function () {
    $scope = SettingsScope::school('school-types');

    Settings::set('t.bool', true, type: 'boolean', label: 'Bool', scope: $scope);
    Settings::set('t.int', 42, type: 'integer', label: 'Int', scope: $scope);
    Settings::set('t.float', 3.14, type: 'float', label: 'Float', scope: $scope);
    Settings::set('t.string', 'abc', type: 'string', label: 'String', scope: $scope);
    Settings::set('t.json', ['a' => 1, 'b' => ['c' => true]], type: 'json', label: 'Json', scope: $scope);
    Settings::set('t.list', [1, 2, 3], type: 'json', label: 'List', scope: $scope);

    expect(Settings::get('t.bool', null, $scope))->toBeTrue()
        ->and(Settings::get('t.int', null, $scope))->toBe(42)
        ->and(Settings::get('t.float', null, $scope))->toBe(3.14)
        ->and(Settings::get('t.string', null, $scope))->toBe('abc')
        ->and(Settings::get('t.json', null, $scope))->toBe(['a' => 1, 'b' => ['c' => true]])
        ->and(Settings::get('t.list', null, $scope))->toBe([1, 2, 3]);
});

test('school scopes are isolated from each other', function () {
    $a = SettingsScope::school('school-a');
    $b = SettingsScope::school('school-b');

    Settings::set('attendance.late_threshold', 10, type: 'integer', label: 'Late', scope: $a);
    Settings::set('attendance.late_threshold', 20, type: 'integer', label: 'Late', scope: $b);

    expect(Settings::get('attendance.late_threshold', null, $a))->toBe(10)
        ->and(Settings::get('attendance.late_threshold', null, $b))->toBe(20);
});

test('user scopes are isolated from each other', function () {
    $a = SettingsScope::user('user-a');
    $b = SettingsScope::user('user-b');

    Settings::set('ui.compact_navigation', true, type: 'boolean', label: 'Compact', scope: $a);
    Settings::set('ui.compact_navigation', false, type: 'boolean', label: 'Compact', scope: $b);

    expect(Settings::get('ui.compact_navigation', null, $a))->toBeTrue()
        ->and(Settings::get('ui.compact_navigation', null, $b))->toBeFalse();
});

test('user and school scopes with the same id fragment do not collide', function () {
    $user = SettingsScope::user('same-id');
    $school = SettingsScope::school('same-id');

    Settings::set('shared.key', 'from-user', type: 'string', label: 'Shared', scope: $user);
    Settings::set('shared.key', 'from-school', type: 'string', label: 'Shared', scope: $school);

    expect(Settings::get('shared.key', null, $user))->toBe('from-user')
        ->and(Settings::get('shared.key', null, $school))->toBe('from-school');
});

test('unique constraint rejects duplicate scope and key', function () {
    $scope = SettingsScope::school('school-unique');

    Setting::query()->create([
        'scope' => $scope,
        'key' => 'only.once',
        'label' => 'Only once',
        'type' => 'string',
        'value' => 'first',
    ]);

    expect(fn () => Setting::query()->create([
        'scope' => $scope,
        'key' => 'only.once',
        'label' => 'Only once',
        'type' => 'string',
        'value' => 'second',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('cache invalidates on update so subsequent reads return the new value', function () {
    $scope = SettingsScope::school('school-cache');

    Settings::set('cache.probe', 'old', type: 'string', label: 'Probe', scope: $scope);
    expect(Settings::get('cache.probe', null, $scope))->toBe('old');

    Settings::set('cache.probe', 'new', type: 'string', label: 'Probe', scope: $scope);
    expect(Settings::get('cache.probe', null, $scope))->toBe('new');
});

test('delete removes override so default is returned', function () {
    $scope = SettingsScope::user('user-reset');

    Settings::set('pref.lang', 'fr', type: 'string', label: 'Lang', scope: $scope);
    expect(Settings::get('pref.lang', 'en', $scope))->toBe('fr');

    Settings::delete('pref.lang', $scope);

    expect(Settings::has('pref.lang', $scope))->toBeFalse()
        ->and(Settings::get('pref.lang', 'en', $scope))->toBe('en');
});

test('settings manager is bound and injectable', function () {
    $manager = app(SettingsManager::class);

    expect($manager)->toBeInstanceOf(SettingsManager::class);

    $manager->set('bound.key', 7, type: 'integer', label: 'Bound', scope: SettingsScope::school('s1'));
    expect($manager->get('bound.key', null, SettingsScope::school('s1')))->toBe(7);
});
