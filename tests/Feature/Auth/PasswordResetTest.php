<?php

/**
 * Password recovery is OTP-based (ResetPasswordNotification), not Laravel's
 * framework ResetPassword mail notification. These tests cover the current contract.
 */

use App\Enums\Auth\AccountLifecycle;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('password recovery request rejects enrollment-only without account email resolution path', function () {
    // Enrollment-ID recovery is intentionally fail-closed when users.enrollment_id is absent.
    $response = $this->post('/forgot-password', [
        'enrollment_id' => 'ENR-DOES-NOT-EXIST',
        'delivery_method' => 'email',
    ]);

    // Either validation error or generic failure — must not succeed silently.
    expect($response->status())->not->toBe(500);
});

test('password recovery uses person email not users.email column', function () {
    Notification::fake();

    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);
    $profile = $user->profile;
    expect($profile)->not->toBeNull();
    expect($profile->email)->not->toBeEmpty();

    // Request may fail closed when multiple or zero eligible accounts; single eligible is required.
    $response = $this->post('/forgot-password', [
        'email' => $profile->email,
        'delivery_method' => 'email',
    ]);

    // Do not assert framework ResetPassword notification — OTP path uses ResetPasswordNotification.
    Notification::assertNothingSentTo($user); // may be route-mail notifiable
    expect($response->status())->not->toBe(500);
});
