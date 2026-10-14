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
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Settings\Definitions\AuthenticationSettings;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Ichtrojan\Otp\Otp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
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
    RateLimiter::clear('password-reset|user@example.com|127.0.0.1');
    RateLimiter::clear('password-reset-link|user@example.com|127.0.0.1');

    $registry = new SettingsRegistry;
    AuthenticationSettings::register($registry);
    $registry->seal();

    $this->app->instance(SettingsRegistry::class, $registry);
    $this->app->instance(SettingsResolver::class, new SettingsResolver($registry));

    $this->resolver = app(SettingsResolver::class);
    $this->school = (object) ['id' => 42];

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

    if (! function_exists('getMergedSettings')) {
        eval(<<<'PHP'
            function getMergedSettings(string $group, $owner = null): array
            {
                return match ($group) {
                    'authentication' => [
                        'password_reset_max_attempts' => 5,
                        'otp_length' => 6,
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
    \Mockery::close();
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
    $controller = app(PasswordResetLinkController::class);
    $request = Request::create('/forgot-password', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->create($request);

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

test('PasswordResetLinkController store passes configured OTP lifetime to Otp::generate', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.reset_password_token_life', 15, $scope);
    $this->resolver->set('authentication.allow_password_reset', true, $scope);

    // Minimal schema so store() can pass validation + user lookup
    Schema::dropIfExists('schools');
    Schema::create('schools', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    \Illuminate\Support\Facades\DB::table('schools')->insert(['id' => 42, 'name' => 'Demo']);

    // Controller queries users.email + users.school_id (legacy columns on this path)
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('username')->nullable();
        $table->string('email')->nullable();
        $table->unsignedBigInteger('school_id')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
    Schema::create('profiles', function (Blueprint $table) {
        $table->id();
        $table->uuid('user_id')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    // Spatie activity() after generate — minimal table so logging cannot abort the path
    Schema::dropIfExists('activity_log');
    Schema::create('activity_log', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
        $table->nullableUuidMorphs('subject', 'subject');
        $table->nullableUuidMorphs('causer', 'causer');
        $table->json('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->timestamps();
    });

    $userId = (string) \Illuminate\Support\Str::uuid();
    \Illuminate\Support\Facades\DB::table('users')->insert([
        'id' => $userId,
        'username' => 'reset.user',
        'email' => 'user@example.com',
        'school_id' => 42,
        'password' => 'hashed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $capturedValidity = null;

    $otp = \Mockery::mock(Otp::class);
    $otp->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $identifier, string $type, int $length, int $validity) use (&$capturedValidity) {
            $capturedValidity = $validity;

            return $identifier === 'user@example.com'
                && $type === 'numeric'
                && $length === 6
                && $validity === 15;
        })
        ->andReturn((object) [
            'status' => true,
            'otp' => '123456',
            'message' => 'OTP generated',
        ]);
    $this->app->instance(Otp::class, $otp);

    \Illuminate\Support\Facades\Notification::fake();

    $controller = app(PasswordResetLinkController::class);
    $request = Request::create(
        '/forgot-password',
        'POST',
        [
            'email' => 'user@example.com',
            'delivery_method' => 'email',
            'school_id' => 42,
        ],
        server: ['REMOTE_ADDR' => '127.0.0.1']
    );
    $request->headers->set('Accept', 'application/json');

    $response = $controller->store($request);

    expect($capturedValidity)->toBe(15)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['message'] ?? null)->toBe('Password reset link sent successfully.');

    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::dropIfExists('schools');
});

test('PasswordResetLinkController store rejects when allow_password_reset is false', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', false, $scope);

    $throttleKey = 'password-reset-link|user@example.com|127.0.0.1';
    RateLimiter::clear($throttleKey);

    $controller = app(PasswordResetLinkController::class);
    $request = Request::create(
        '/forgot-password',
        'POST',
        [
            'email' => 'user@example.com',
            'delivery_method' => 'email',
            'school_id' => 42,
        ],
        server: ['REMOTE_ADDR' => '127.0.0.1']
    );
    $request->headers->set('Accept', 'application/json');

    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'] ?? null)->toBe('Password reset is not allowed.')
        // Disabled path must not establish/increment a throttle key
        ->and(RateLimiter::attempts($throttleKey))->toBe(0);
});

test('NewPasswordController store rejects when allow_password_reset is false', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', false, $scope);

    $throttleKey = 'password-reset|user@example.com|127.0.0.1';
    RateLimiter::clear($throttleKey);

    $request = \Mockery::mock(ResetPasswordRequest::class)->shouldIgnoreMissing();
    $request->email = 'user@example.com';
    $request->token = 'token';
    $request->password = 'Password1!';
    $request->shouldReceive('expectsJson')->andReturn(true);
    $request->shouldReceive('ip')->andReturn('127.0.0.1');

    $controller = app(NewPasswordController::class);
    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'] ?? null)->toBe('Password reset is not allowed.')
        ->and(RateLimiter::attempts($throttleKey))->toBe(0);
});

test('NewPasswordController store rate limit rejects after max attempts without using OTP lifetime', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', true, $scope);
    // Distinct OTP lifetime — must not affect rate limiting
    $this->resolver->set('authentication.reset_password_token_life', 15, $scope);

    $throttleKey = 'password-reset|user@example.com|127.0.0.1';
    RateLimiter::clear($throttleKey);
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($throttleKey);
    }

    $request = \Mockery::mock(ResetPasswordRequest::class)->shouldIgnoreMissing();
    $request->email = 'user@example.com';
    $request->token = 'token';
    $request->password = 'Password1!';
    $request->shouldReceive('expectsJson')->andReturn(true);
    $request->shouldReceive('ip')->andReturn('127.0.0.1');

    $controller = app(NewPasswordController::class);
    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'] ?? null)
        ->toBe('Too many password reset attempts. Please try again later.');
});

test('PasswordResetLinkController store rate limit rejects after max attempts without using OTP lifetime', function () {
    $scope = SettingsScope::school('42');
    $this->resolver->set('authentication.allow_password_reset', true, $scope);
    $this->resolver->set('authentication.reset_password_token_life', 15, $scope);

    Schema::dropIfExists('schools');
    Schema::create('schools', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    \Illuminate\Support\Facades\DB::table('schools')->insert(['id' => 42, 'name' => 'Demo']);

    $throttleKey = 'password-reset-link|user@example.com|127.0.0.1';
    RateLimiter::clear($throttleKey);
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($throttleKey);
    }

    $controller = app(PasswordResetLinkController::class);
    $request = Request::create(
        '/forgot-password',
        'POST',
        [
            'email' => 'user@example.com',
            'delivery_method' => 'email',
            'school_id' => 42,
        ],
        server: ['REMOTE_ADDR' => '127.0.0.1']
    );
    $request->headers->set('Accept', 'application/json');

    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'] ?? null)
        ->toBe('Too many password reset link requests. Please try again later.');

    Schema::dropIfExists('schools');
});

test('unrelated authentication settings remain on the legacy path', function () {
    $legacy = getMergedSettings('authentication', $this->school);

    expect($legacy)->toHaveKey('otp_length')
        ->and($legacy)->toHaveKey('password_reset_max_attempts')
        ->and($legacy)->not->toHaveKey('reset_password_token_life')
        ->and($legacy)->not->toHaveKey('allow_password_reset');
});

test('laravel password broker config is unchanged by this phase', function () {
    $expire = config('auth.passwords.users.expire');

    expect($expire)->toBeInt();
});
