<?php

use App\Enums\Auth\AccountLifecycle;
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

test('login throttle limit is application scoped not school scoped', function () {
    config(['auth.login_throttle_max' => 3]);

    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
    ]);

    RateLimiter::clear(strtolower($user->username).'|127.0.0.1');

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', [
            'login' => $user->username,
            'password' => 'wrong-password',
        ], [
            'X-School-Id' => '00000000-0000-0000-0000-0000000000aa',
        ]);
    }

    $response = $this->post('/login', [
        'login' => $user->username,
        'password' => 'wrong-password',
    ], [
        'X-School-Id' => '00000000-0000-0000-0000-0000000000bb',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});
