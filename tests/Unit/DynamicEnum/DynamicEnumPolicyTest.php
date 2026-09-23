<?php

/**
 * Dynamic Enum Phase 4 — policy authorization tests.
 */

uses(Tests\TestCase::class);

use App\Models\User;
use App\Policies\DynamicEnumPolicy;

beforeEach(function () {
    $this->policy = new DynamicEnumPolicy;
});

function phase4UserWithPermissions(array $permissions): User
{
    $user = \Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasPermission')->andReturnUsing(
        fn (string $perm) => in_array($perm, $permissions, true)
    );

    return $user;
}

test('viewAny allowed with view permission', function () {
    $user = phase4UserWithPermissions(['dynamic-enums.view']);
    expect($this->policy->viewAny($user)->allowed())->toBeTrue();
});

test('viewAny denied without permissions', function () {
    $user = phase4UserWithPermissions([]);
    expect($this->policy->viewAny($user)->allowed())->toBeFalse();
});

test('manage allowed with manage permission', function () {
    $user = phase4UserWithPermissions(['dynamic-enums.manage']);
    expect($this->policy->manage($user)->allowed())->toBeTrue();
});

test('manageGlobals not granted by manage alone', function () {
    $user = phase4UserWithPermissions(['dynamic-enums.manage']);
    expect($this->policy->manageGlobals($user)->allowed())->toBeFalse();
});

test('manageGlobals allowed with manageGlobals permission', function () {
    $user = phase4UserWithPermissions(['dynamic-enums.manageGlobals']);
    expect($this->policy->manageGlobals($user)->allowed())->toBeTrue();
});
