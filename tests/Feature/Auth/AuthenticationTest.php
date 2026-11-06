<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'force_password_change' => false,
    ]);

    $response = $this->post('/login', [
        'login' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);

    $this->post('/login', [
        'login' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('login throttle is application scoped across real school contexts', function () {
    config(['auth.login_throttle_max' => 3]);

    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user->schools()->attach([$schoolA->id, $schoolB->id]);

    $key = \Illuminate\Support\Str::transliterate(strtolower($user->username).'|127.0.0.1');
    RateLimiter::clear($key);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', [
            'login' => $user->username,
            'password' => 'wrong-password',
            'school_id' => $schoolA->id,
        ], [
            'X-School-Id' => $schoolA->id,
        ]);
    }

    // Different real School must not grant additional attempts for same login|IP.
    $response = $this->post('/login', [
        'login' => $user->username,
        'password' => 'wrong-password',
        'school_id' => $schoolB->id,
    ], [
        'X-School-Id' => $schoolB->id,
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
    expect(RateLimiter::tooManyAttempts($key, 3))->toBeTrue();
});
