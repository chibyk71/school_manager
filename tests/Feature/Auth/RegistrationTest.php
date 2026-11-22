<?php

test('registration screen is blocked when registration is disabled', function () {
    $response = $this->get('/register');

    // Settings default allow_user_registration=false → error redirect/forbidden.
    expect(in_array($response->status(), [302, 403], true))->toBeTrue();
    $this->assertGuest();
});

test('public registration store is disabled until person onboarding is approved', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ]);

    $this->assertGuest();
    $response->assertForbidden();
});
