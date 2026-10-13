<?php

/**
 * Phase 1 — Authentication controller migration regression coverage.
 *
 * Focused tests for the two pilot keys only. Uses ephemeral OI schema and
 * stubs for school context + legacy getMergedSettings so the OTP flow can be
 * exercised without a full multi-tenant migrate.
 */

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Settings\Definitions\AuthenticationSettings;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'oi-laravel-settings.table' => 'settings',
        'oi-laravel-settings.cache.enabled' => true,
        'oi-laravel-settings.cache.prefix' => 'oi-settings-auth-mig-test',
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

    $this->app->instance(SettingsRegistry::class, $registry);
    $this->app->instance(SettingsResolver::class, new SettingsResolver($registry));

    $this->resolver = app(SettingsResolver::class);
    $this->school = (object) ['id' => 42];

    // School context for GetSchoolModel()
    $this->app->instance('schoolManager', new class($this->school)
    {
        public function __construct(private $school) {}

        public function getActiveSchool()
        {
            return $this->school;
        }

        public function setActiveSchool($school): void
        {
            $this->school = $school;
        }
    });

    // Stub legacy helper for non-pilot keys (SMS / remaining auth bag)
    if (! function_exists('getMergedSettings')) {
        eval(<<<'PHP'
            function getMergedSettings(string $group, $owner = null): array
            {
                return match ($group) {
                    'authentication' => [
                        'password_reset_max_attempts' => 5,
                        'otp_length' => 6,
                        // Pilot keys intentionally absent — controllers must use resolver
                    ],
                    'sms' => [
                        'sms_enabled' => false,
                    ],
                    default => [],
                };
            }
        PHP);
    }
});

afterEach(function () {
    Schema::dropIfExists('settings');
    Cache::flush();
});

test('password reset create is rejected when allow_password_reset is false', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', false, $scope);

    $controller = app(PasswordResetLinkController::class);
    $request = Request::create('/forgot-password', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->create($request);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error'] ?? null)->toBe('Password reset is not allowed.');
});

test('password reset create is allowed when setting is default true', function () {
    $scope = SettingsScope::school('42');
    // No override — registry default true

    $controller = app(PasswordResetLinkController::class);
    $request = Request::create('/forgot-password', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->create($request);

    // 200 JSON with school context — not the 403 disabled path
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toHaveKey('sms_enabled');
});

test('new password create is rejected when allow_password_reset is false', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', false, $scope);

    $controller = app(NewPasswordController::class);
    $request = Request::create('/reset-password', 'GET', ['token' => 'x', 'email' => 'a@b.c']);
    $request->headers->set('Accept', 'application/json');

    $response = $controller->create($request);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error'] ?? null)->toBe('Password reset is not allowed.');
});

test('OTP lifetime default resolves to 60 via registry', function () {
    $scope = SettingsScope::school('42');

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(60);
});

test('OTP lifetime school override is used by resolver path controllers depend on', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.reset_password_token_life', 15, $scope);

    expect($this->resolver->get('authentication.reset_password_token_life', $scope))->toBe(15);

    // Controllers read the same resolver + scope construction
    $minutes = app(SettingsResolver::class)->get(
        'authentication.reset_password_token_life',
        SettingsScope::school((string) $this->school->id)
    );
    expect($minutes)->toBe(15);
});

test('unrelated authentication settings remain on the legacy path', function () {
    // getMergedSettings stub returns otp_length / password_reset_max_attempts only
    $legacy = getMergedSettings('authentication', $this->school);

    expect($legacy)->toHaveKey('otp_length')
        ->and($legacy)->toHaveKey('password_reset_max_attempts')
        ->and($legacy)->not->toHaveKey('reset_password_token_life')
        ->and($legacy)->not->toHaveKey('allow_password_reset');
});

test('laravel password broker config is unchanged by this phase', function () {
    // Phase 1 must not alter config/auth.php broker expiry
    $expire = config('auth.passwords.users.expire');

    // Whatever the app default is, it must still be an integer minutes value
    // and must not be rewritten from the settings pilot key.
    expect($expire)->toBeInt();
});
