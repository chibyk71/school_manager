<?php

/**
 * OTP password recovery against eligible Authentication Accounts.
 *
 * Public initiation responses must be identical for eligible and ineligible emails.
 * Eligibility: linked non-deleted Person + auth_email_verified_at + auth_email_enabled.
 */

use App\Enums\Auth\AccountLifecycle;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Auth\AuthenticationEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
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

test('eligible and ineligible initiation responses are indistinguishable over json', function () {
    Notification::fake();

    $eligible = eligibleUser();
    $eligibleEmail = $eligible->profile->email;

    $ineligible = eligibleUser([
        'auth_email_verified_at' => null,
        'auth_email_enabled' => true,
    ]);
    $ineligibleEmail = $ineligible->profile->email;

    $ok = $this->postJson('/forgot-password', [
        'email' => $eligibleEmail,
        'delivery_method' => 'email',
    ]);
    $denied = $this->postJson('/forgot-password', [
        'email' => $ineligibleEmail,
        'delivery_method' => 'email',
    ]);
    $unknown = $this->postJson('/forgot-password', [
        'email' => 'nobody-exists-'.uniqid().'@example.com',
        'delivery_method' => 'email',
    ]);

    foreach ([$ok, $denied, $unknown] as $response) {
        $response->assertOk();
        $response->assertJsonStructure(['message']);
        expect($response->json('message'))->toBe(
            'If an eligible account exists for this email, a reset code has been sent.'
        );
    }

    Notification::assertSentOnDemand(ResetPasswordNotification::class, function ($n, $channels, $notifiable) use ($eligibleEmail) {
        return ($notifiable->routes['mail'] ?? null) === AuthenticationEmail::normalize($eligibleEmail);
    });
});

test('unverified authentication email does not receive otp', function () {
    Notification::fake();
    $user = eligibleUser([
        'auth_email_verified_at' => null,
        'auth_email_enabled' => true,
    ]);

    $this->postJson('/forgot-password', [
        'email' => $user->profile->email,
        'delivery_method' => 'email',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('disabled authentication email does not receive otp', function () {
    Notification::fake();
    $user = eligibleUser([
        'auth_email_verified_at' => now(),
        'auth_email_enabled' => false,
    ]);

    $this->postJson('/forgot-password', [
        'email' => $user->profile->email,
        'delivery_method' => 'email',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('soft-deleted person does not receive otp', function () {
    Notification::fake();
    $user = eligibleUser();
    $email = $user->profile->email;
    $user->profile->delete();

    $this->postJson('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('shared email matching multiple eligible accounts fails closed without otp', function () {
    Notification::fake();
    $email = 'shared-recovery@example.com';

    $a = eligibleUser();
    $b = eligibleUser();
    $a->profile->update(['email' => $email]);
    $b->profile->update(['email' => $email]);

    $this->postJson('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('mixed-case email initiation uses normalized address for otp delivery', function () {
    Notification::fake();
    $user = eligibleUser();
    $mixed = strtoupper(substr($user->profile->email, 0, 3)).substr($user->profile->email, 3);
    $normalized = AuthenticationEmail::normalize($mixed);

    $this->postJson('/forgot-password', [
        'email' => $mixed,
        'delivery_method' => 'email',
    ])->assertOk();

    Notification::assertSentOnDemand(ResetPasswordNotification::class, function ($notification, $channels, $notifiable) use ($normalized) {
        return ($notifiable->routes['mail'] ?? null) === $normalized
            && $notification->token !== '';
    });
});

test('invalid otp increments attempts exactly once and blocked requests do not increment further', function () {
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

    for ($i = 0; $i < 4; $i++) {
        $this->post('/reset-password', [
            'email' => $email,
            'token' => '111111',
            'password' => 'brand-new-password-99',
            'password_confirmation' => 'brand-new-password-99',
        ]);
    }
    expect(RateLimiter::attempts($key))->toBe(5);
    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();

    // Already locked: must not grow the counter.
    $this->post('/reset-password', [
        'email' => $email,
        'token' => '222222',
        'password' => 'brand-new-password-99',
        'password_confirmation' => 'brand-new-password-99',
    ]);
    expect(RateLimiter::attempts($key))->toBe(5);

    $user->refresh();
    expect($user->password)->toBe($original);
});

test('otp lock expires after decay window', function () {
    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);
    $key = 'password-reset|'.$email.'|127.0.0.1';
    RateLimiter::clear($key);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 60 * 60);
    }
    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();

    $this->travel(61)->minutes();

    expect(RateLimiter::tooManyAttempts($key, 5))->toBeFalse();
});

test('expired otp is rejected after controlled time travel', function () {
    Event::fake([PasswordReset::class]);

    $user = eligibleUser();
    $email = AuthenticationEmail::normalize($user->profile->email);
    $key = 'password-reset|'.$email.'|127.0.0.1';
    RateLimiter::clear($key);
    $original = $user->password;
    $lifecycle = $user->lifecycle;

    $otp = app(Otp::class);
    // Package validity is in minutes; generate a short-lived OTP then advance past it.
    $generated = $otp->generate($email, 'numeric', 6, 1);
    $code = is_object($generated)
        ? (string) ($generated->token ?? $generated->otp ?? '')
        : (string) $generated;

    expect($code)->not->toBeEmpty('OTP package must return a token for expiry testing');

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
    expect($user->lifecycle)->toEqual($lifecycle);
    expect(RateLimiter::attempts($key))->toBe(1);
    Event::assertNotDispatched(PasswordReset::class);
});

test('valid otp recovery leaves provisioned lifecycle unchanged', function () {
    Event::fake([PasswordReset::class]);

    $user = eligibleUser([
        'lifecycle' => AccountLifecycle::Provisioned,
    ]);
    expect($user->lifecycle)->toBe(AccountLifecycle::Provisioned);

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
    expect($user->lifecycle)->toBe(AccountLifecycle::Provisioned);
    expect(RateLimiter::attempts($key))->toBe(0);
    Event::assertDispatched(PasswordReset::class);
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
