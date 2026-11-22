<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    // Screen may still render; store is intentionally disabled in Phase 1.
    $response->assertOk();
});

test('public registration is disabled until person onboarding is approved', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertForbidden();
});
