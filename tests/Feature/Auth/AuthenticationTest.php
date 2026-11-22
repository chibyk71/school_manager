<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $this->withoutVite();

    $response = $this->get('/login');

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'password' => 'password',
    ]);

    $response = $this->post('/login', [
        'login' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'password' => 'password',
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
    $response->assertRedirect();
});
