<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\User;

test('confirm password screen can be rendered', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertOk();
});

test('password can be confirmed', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create([
        'lifecycle' => AccountLifecycle::Active,
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
