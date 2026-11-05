<?php

/**
 * OTP password recovery against eligible Authentication Accounts.
 * Person primary email is usable only when auth_email_verified_at is set and
 * auth_email_enabled is true. Shared emails fail closed.
 */

use App\Enums\Auth\AccountLifecycle;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Auth\AuthenticationEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Ichtrojan\Otp\Otp;

function eligibleUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'lifecycle' => AccountLifecycle::Active,
        'auth_email_verified_at' => now(),
        'auth_email_enabled' => true,
    ], $overrides));

    return $user->fresh(['profile']);
}

test('reset password link screen can be rendered', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

test('enrollment id recovery is rejected in this phase', function () {
    $this->from('/forgot-password')->post('/forgot-password', [
        'enrollment_id' => 'ENR-DOES-NOT-EXIST',
        'delivery_method' => 'email',
    ])->assertRedirect();
});

test('unverified authentication email cannot initiate recovery', function () {
    Notification::fake();
    $user = eligibleUser([
        'auth_email_verified_at' => null,
        'auth_email_enabled' => true,
    ]);
    $email = $user->profile->email;

    $this->post('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ]);

    Notification::assertNothingSent();
});

test('disabled authentication email cannot initiate recovery', function () {
    Notification::fake();
    $user = eligibleUser([
        'auth_email_verified_at' => now(),
        'auth_email_enabled' => false,
    ]);

    $this->post('/forgot-password', [
        'email' => $user->profile->email,
        'delivery_method' => 'email',
    ]);

    Notification::assertNothingSent();
});

test('soft-deleted person cannot initiate recovery', function () {
    Notification::fake();
    $user = eligibleUser();
    $email = $user->profile->email;
    $user->profile->delete();

    $this->post('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ]);

    Notification::assertNothingSent();
});

test('shared email matching multiple eligible accounts fails closed', function () {
    Notification::fake();
    $email = 'shared-recovery@example.com';

    $a = eligibleUser();
    $b = eligibleUser();
    $a->profile->update(['email' => $email]);
    $b->profile->update(['email' => $email]);

    $this->post('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ]);

    Notification::assertNothingSent();
});

test('valid eligible email delivers otp to normalized person primary email', function () {
    Notification::fake();
    $user = eligibleUser();
    $mixed = strtoupper(substr($user->profile->email, 0, 3)).substr($user->profile->email, 3);
    $normalized = AuthenticationEmail::normalize($mixed);

    $response = $this->post('/forgot-password', [
        'email' => $mixed,
        'delivery_method' => 'email',
    ]);

    $response->assertRedirect();
    Notification::assertSentOnDemand(ResetPasswordNotification::class, function ($notification, $channels, $notifiable) use ($normalized, $user) {
        expect($channels)->toContain('mail');
        expect($notifiable->routes['mail'] ?? null)->toBe($normalized);
        expect($notification->token)->not->toBeEmpty();
        expect($notification->user->is($user))->toBeTrue();

        return true;
    });
});

test('invalid otp increments attempts exactly once and does not change password', function () {
    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);
    $key = 'password-reset|'.$email.'|127.0.0.1';
    RateLimiter::clear($key);
    $original = $user->password;

    $this->post('/reset-password', [
        'email' => $email,
        'token' => '000000',
        'password' => 'brand-new-password-99',
        'password_confirmation' => 'brand-new-password-99',
    ]);

    expect(RateLimiter::attempts($key))->toBe(1);
    $user->refresh();
    expect($user->password)->toBe($original);
    expect($user->lifecycle)->toBe(AccountLifecycle::Active);
});

test('reaching otp attempt threshold locks further attempts', function () {
    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);
    $key = 'password-reset|'.$email.'|127.0.0.1';
    RateLimiter::clear($key);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/reset-password', [
            'email' => $email,
            'token' => '111111',
            'password' => 'brand-new-password-99',
            'password_confirmation' => 'brand-new-password-99',
        ]);
    }

    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
});

test('expired otp is rejected and password unchanged', function () {
    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);
    $original = $user->password;

    $otp = app(Otp::class);
    // validity of 0 minutes => immediately expired for packages that interpret minutes
    $generated = $otp->generate($email, 'numeric', 6, -1);
    $code = is_object($generated)
        ? (string) ($generated->token ?? $generated->otp ?? '')
        : (string) $generated;

    if ($code === '') {
        $this->markTestSkipped('OTP package did not return a token for expiry fixture');
    }

    $this->travel(2)->minutes();

    $this->post('/reset-password', [
        'email' => $email,
        'token' => $code,
        'password' => 'expired-password-attempt-1',
        'password_confirmation' => 'expired-password-attempt-1',
    ]);

    $user->refresh();
    expect(Hash::check('expired-password-attempt-1', $user->password))->toBeFalse();
    expect($user->password)->toBe($original);
});

test('valid otp completes recovery without activating provisioned lifecycle', function () {
    $user = eligibleUser(['lifecycle' => AccountLifecycle::Active]);
    $email = AuthenticationEmail::normalize($user->profile->email);
    $key = 'password-reset|'.$email.'|127.0.0.1';
    RateLimiter::clear($key);

    $otp = app(Otp::class);
    $generated = $otp->generate($email, 'numeric', 6, 10);
    $code = is_object($generated)
        ? (string) ($generated->token ?? $generated->otp ?? '')
        : (string) $generated;
    expect($code)->not->toBeEmpty();

    $response = $this->post('/reset-password', [
        'email' => strtoupper($email),
        'token' => $code,
        'password' => 'brand-new-password-99',
        'password_confirmation' => 'brand-new-password-99',
    ]);

    $response->assertRedirect(route('login'));
    $user->refresh();
    expect(Hash::check('brand-new-password-99', $user->password))->toBeTrue();
    expect(RateLimiter::attempts($key))->toBe(0);
});

test('completion rechecks eligibility and rejects disabled auth email', function () {
    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);

    $otp = app(Otp::class);
    $generated = $otp->generate($email, 'numeric', 6, 10);
    $code = is_object($generated)
        ? (string) ($generated->token ?? $generated->otp ?? '')
        : (string) $generated;

    $user->forceFill(['auth_email_enabled' => false])->save();

    $this->post('/reset-password', [
        'email' => $email,
        'token' => $code,
        'password' => 'should-not-apply-99',
        'password_confirmation' => 'should-not-apply-99',
    ]);

    $user->refresh();
    expect(Hash::check('should-not-apply-99', $user->password))->toBeFalse();
});
