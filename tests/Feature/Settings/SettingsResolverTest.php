<?php

/**
 * Phase 1 — SettingsResolver integration coverage (exact-scope isolation).
 *
 * Creates the OI settings schema ephemerally (same practice as SettingsInfrastructureTest).
 */

use App\Settings\Definitions\AuthenticationSettings;
use App\Support\Settings\Exceptions\InvalidSettingValueException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use OiLab\OiLaravelSettings\Facades\Settings as OiSettings;

beforeEach(function () {
    config([
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'oi-laravel-settings.table' => 'settings',
        'oi-laravel-settings.cache.enabled' => true,
        'oi-laravel-settings.cache.prefix' => 'oi-settings-resolver-test',
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
    AuthenticationSettings::register($registry);
    $registry->seal();

    $this->registry = $registry;
    $this->resolver = new SettingsResolver($registry);
});

afterEach(function () {
    Schema::dropIfExists('settings');
    Cache::flush();
});

test('defaults resolve without overrides', function () {
    $scope = SettingsScope::school('school-1');

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60)
        ->and($this->resolver->get('authentication.allow_password_reset', $scope))->toBeTrue();
});

test('school-specific override resolves correctly', function () {
    $scope = SettingsScope::school('school-1');

    $this->resolver->set('authentication.reset_password_token_life', 90, $scope);

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(90);
});

test('school A override does not leak into school B', function () {
    $a = SettingsScope::school('school-a');
    $b = SettingsScope::school('school-b');

    $this->resolver->set('authentication.reset_password_token_life', 90, $a);

    expect($this->resolver->get('authentication.reset_password_token_life', $a))->toBe(90)
        ->and($this->resolver->get('authentication.reset_password_token_life', $b))->toBe(60);
});

test('global-only value is ignored for school-scoped pilot keys', function () {
    $scope = SettingsScope::school('school-1');

    OiSettings::set(
        'authentication.reset_password_token_life',
        120,
        type: 'integer',
        label: 'Password reset OTP lifetime (minutes)',
        scope: null,
    );

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60);
});

test('when both global and school values exist, school value wins', function () {
    $scope = SettingsScope::school('school-1');

    OiSettings::set(
        'authentication.reset_password_token_life',
        120,
        type: 'integer',
        label: 'Password reset OTP lifetime (minutes)',
        scope: null,
    );

    $this->resolver->set('authentication.reset_password_token_life', 90, $scope);

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(90);
});

test('exact-scope matrix for reset_password_token_life', function () {
    $scope = SettingsScope::school('matrix-school');

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60);

    $this->resolver->set('authentication.reset_password_token_life', 90, $scope);
    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(90);

    $this->resolver->forget('authentication.reset_password_token_life', $scope);
    OiSettings::set(
        'authentication.reset_password_token_life',
        120,
        type: 'integer',
        label: 'Password reset OTP lifetime (minutes)',
        scope: null,
    );
    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60);

    $this->resolver->set('authentication.reset_password_token_life', 90, $scope);
    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(90);
});

test('forgetting an override restores the registry default', function () {
    $scope = SettingsScope::school('school-forget');

    $this->resolver->set('authentication.allow_password_reset', false, $scope);
    expect($this->resolver->get('authentication.allow_password_reset', $scope))->toBeFalse();

    $this->resolver->forget('authentication.allow_password_reset', $scope);
    expect($this->resolver->get('authentication.allow_password_reset', $scope))->toBeTrue();
});

test('invalid integer writes throw and preserve existing value', function () {
    $scope = SettingsScope::school('school-val');

    $this->resolver->set('authentication.reset_password_token_life', 45, $scope);

    expect(fn () => $this->resolver->set('authentication.reset_password_token_life', 60.5, $scope))
        ->toThrow(InvalidSettingValueException::class);

    expect(fn () => $this->resolver->set('authentication.reset_password_token_life', '60.0', $scope))
        ->toThrow(InvalidSettingValueException::class);

    expect(fn () => $this->resolver->set('authentication.reset_password_token_life', [60], $scope))
        ->toThrow(InvalidSettingValueException::class);

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(45);
});

test('valid integer string is accepted', function () {
    $scope = SettingsScope::school('school-int-str');

    $this->resolver->set('authentication.reset_password_token_life', '75', $scope);

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(75);
});

test('invalid boolean writes throw and preserve existing value', function () {
    $scope = SettingsScope::school('school-bool');

    $this->resolver->set('authentication.allow_password_reset', false, $scope);

    expect(fn () => $this->resolver->set('authentication.allow_password_reset', 'yes', $scope))
        ->toThrow(InvalidSettingValueException::class);

    expect(fn () => $this->resolver->set('authentication.allow_password_reset', ['true'], $scope))
        ->toThrow(InvalidSettingValueException::class);

    expect($this->resolver->get('authentication.allow_password_reset', $scope))->toBeFalse();
});

test('boolean accepts every approved whitelist representation', function () {
    $scope = SettingsScope::school('school-bool-ok');

    $trueValues = [true, 1, '1', 'true', 'on'];
    $falseValues = [false, 0, '0', 'false', 'off'];

    foreach ($trueValues as $value) {
        $this->resolver->set('authentication.allow_password_reset', $value, $scope);
        expect($this->resolver->get('authentication.allow_password_reset', $scope))
            ->toBeTrue('Failed for true representation: '.var_export($value, true));
    }

    foreach ($falseValues as $value) {
        $this->resolver->set('authentication.allow_password_reset', $value, $scope);
        expect($this->resolver->get('authentication.allow_password_reset', $scope))
            ->toBeFalse('Failed for false representation: '.var_export($value, true));
    }
});

test('boolean rejects representative invalid values', function () {
    $scope = SettingsScope::school('school-bool-bad');

    $this->resolver->set('authentication.allow_password_reset', true, $scope);

    foreach (['yes', 'no', 'TRUE', 'FALSE', 2, -1, '', ' '] as $value) {
        expect(fn () => $this->resolver->set('authentication.allow_password_reset', $value, $scope))
            ->toThrow(InvalidSettingValueException::class);
    }

    expect($this->resolver->get('authentication.allow_password_reset', $scope))->toBeTrue();
});

test('malformed stored integer is detected before OI coercion', function () {
    $scope = SettingsScope::school('school-corrupt-int');

    \Illuminate\Support\Facades\DB::table('settings')->insert([
        'scope' => $scope,
        'key' => 'authentication.reset_password_token_life',
        'label' => 'Password reset OTP lifetime (minutes)',
        'type' => 'integer',
        'value' => 'not-an-integer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    OiSettings::forget($scope);

    expect(fn () => $this->resolver->get('authentication.reset_password_token_life', $scope))
        ->toThrow(\App\Support\Settings\Exceptions\InvalidStoredSettingValueException::class);
});

test('malformed stored boolean is detected before OI coercion', function () {
    $scope = SettingsScope::school('school-corrupt-bool');

    \Illuminate\Support\Facades\DB::table('settings')->insert([
        'scope' => $scope,
        'key' => 'authentication.allow_password_reset',
        'label' => 'Allow password reset',
        'type' => 'boolean',
        'value' => 'yes',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    OiSettings::forget($scope);

    expect(fn () => $this->resolver->get('authentication.allow_password_reset', $scope))
        ->toThrow(\App\Support\Settings\Exceptions\InvalidStoredSettingValueException::class);
});

test('fractional stored integer string is rejected as corrupt', function () {
    $scope = SettingsScope::school('school-corrupt-float');

    \Illuminate\Support\Facades\DB::table('settings')->insert([
        'scope' => $scope,
        'key' => 'authentication.reset_password_token_life',
        'label' => 'Password reset OTP lifetime (minutes)',
        'type' => 'integer',
        'value' => '60.5',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    OiSettings::forget($scope);

    expect(fn () => $this->resolver->get('authentication.reset_password_token_life', $scope))
        ->toThrow(\App\Support\Settings\Exceptions\InvalidStoredSettingValueException::class);
});

test('unknown keys fail on get and set', function () {
    $scope = SettingsScope::school('school-1');

    expect(fn () => $this->resolver->get('authentication.missing', $scope))
        ->toThrow(UnknownSettingException::class);

    expect(fn () => $this->resolver->set('authentication.missing', 1, $scope))
        ->toThrow(UnknownSettingException::class);
});

test('cache reflects successful writes and forget operations', function () {
    $scope = SettingsScope::school('school-cache');

    $this->resolver->set('authentication.reset_password_token_life', 99, $scope);
    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(99);

    $this->resolver->forget('authentication.reset_password_token_life', $scope);
    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60);
});
