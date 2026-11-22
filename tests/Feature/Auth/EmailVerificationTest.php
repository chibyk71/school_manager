<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\User;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Support\Facades\RateLimiter;
use Ichtrojan\Otp\Otp;

test('email verification screen can be rendered', function () {
    $this->withoutVite();

    $user = User::factory()->unverified()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertOk();
});

test('auth email can be verified with valid otp', function () {
    $accounts = app(UserAccountService::class);
    $person = \App\Models\Profile::factory()->create(['email' => 'verify-otp@example.com']);
    $user = $accounts->createForPerson($person, [
        'username' => 'verify_otp_user',
        'password' => 'password123',
    ], ['lifecycle' => AccountLifecycle::Active, 'send_notification' => false]);

    RateLimiter::clear('email-verify-otp:'.$user->id);

    $otp = app(Otp::class)->generate('verify-otp@example.com', 'numeric', 6, 10);
    $token = is_object($otp) ? (string) ($otp->token ?? $otp->otp ?? '') : (string) $otp;

    $response = $this->actingAs($user)->postJson('/verify-email', [
        'otp' => $token,
    ]);

    $response->assertOk();
    $user->refresh();
    expect($user->auth_email_verified_at)->not->toBeNull();
    // Verification must not auto-enable email login.
    expect($user->auth_email_enabled)->toBeFalse();
});

test('auth email is not verified with invalid otp', function () {
    $accounts = app(UserAccountService::class);
    $person = \App\Models\Profile::factory()->create(['email' => 'bad-otp@example.com']);
    $user = $accounts->createForPerson($person, [
        'username' => 'bad_otp_user',
        'password' => 'password123',
    ], ['lifecycle' => AccountLifecycle::Active, 'send_notification' => false]);

    RateLimiter::clear('email-verify-otp:'.$user->id);

    $response = $this->actingAs($user)->postJson('/verify-email', [
        'otp' => '000000',
    ]);

    $response->assertStatus(422);
    $user->refresh();
    expect($user->auth_email_verified_at)->toBeNull();
});
