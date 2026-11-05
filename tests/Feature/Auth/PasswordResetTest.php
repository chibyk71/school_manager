<?php

/**
 * OTP-based password recovery (ResetPasswordNotification via on-demand mail route).
 * Not Laravel framework ResetPassword notification.
 */

use App\Enums\Auth\AccountLifecycle;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Ichtrojan\Otp\Otp;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('enrollment id recovery is rejected in this phase', function () {
    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'enrollment_id' => 'ENR-DOES-NOT-EXIST',
        'delivery_method' => 'email',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

test('valid person email delivers otp notification to person primary email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);
    $profile = $user->profile;
    expect($profile)->not->toBeNull();
    $email = $profile->email;
    expect($email)->not->toBeEmpty();

    $response = $this->post('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(ResetPasswordNotification::class, function ($notification, $channels, $notifiable) use ($email, $user) {
        expect($channels)->toContain('mail');
        expect($notifiable->routes['mail'] ?? null)->toBe($email);
        expect($notification->token)->not->toBeEmpty();
        expect($notification->user->is($user))->toBeTrue();

        return true;
    });
});

test('shared email matching multiple linked accounts fails closed', function () {
    Notification::fake();

    $email = 'shared-recovery@example.com';

    $userA = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $userB = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $userA->profile->update(['email' => $email]);
    $userB->profile->update(['email' => $email]);

    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => $email,
        'delivery_method' => 'email',
    ]);

    $response->assertRedirect();
    Notification::assertNothingSent();
});

test('invalid otp cannot reset password', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $email = $user->profile->email;

    $response = $this->from('/reset-password')->post('/reset-password', [
        'email' => $email,
        'token' => '000000',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeFalse();
});

test('valid otp completes password reset workflow', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $email = $user->profile->email;

    $otp = app(Otp::class);
    $generated = $otp->generate($email, 'numeric', 6, 10);
    $token = $generated->token ?? $generated->otp ?? null;
    if ($token === null && is_object($generated)) {
        $token = $generated->token ?? null;
    }
    // ichtrojan/otp generate returns object with ->token or ->otp depending on version
    $code = is_object($generated) ? ($generated->token ?? $generated->otp ?? (string) $generated) : (string) $generated;

    if (! is_string($code) || $code === '') {
        // Fallback: read latest otp row if table exists
        $code = \Illuminate\Support\Facades\DB::table('otps')->where('identifier', $email)->value('token');
    }

    expect($code)->not->toBeEmpty();

    $response = $this->post('/reset-password', [
        'email' => $email,
        'token' => $code,
        'password' => 'brand-new-password-99',
        'password_confirmation' => 'brand-new-password-99',
    ]);

    $response->assertRedirect(route('login'));
    $user->refresh();
    expect(Hash::check('brand-new-password-99', $user->password))->toBeTrue();
});
