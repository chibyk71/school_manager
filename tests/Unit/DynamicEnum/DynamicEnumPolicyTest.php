<?php

/**
 * Dynamic Enum Phase 7 — policy authorization tests (scope-neutral capabilities).
 */

uses(Tests\TestCase::class);

use App\Models\User;
use App\Policies\DynamicEnumPolicy;

beforeEach(function () {
    $this->policy = new DynamicEnumPolicy;
});

function phase7UserWithPermissions(array $permissions): User
{
    $user = \Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasPermission')->andReturnUsing(
        fn (string $perm) => in_array($perm, $permissions, true)
    );

    return $user;
}

test('viewAny allowed with view permission', function () {
    $user = phase7UserWithPermissions(['dynamic-enums.view']);
    expect($this->policy->viewAny($user)->allowed())->toBeTrue();
});

test('viewAny allowed with manage permission', function () {
    $user = phase7UserWithPermissions(['dynamic-enums.manage']);
    expect($this->policy->viewAny($user)->allowed())->toBeTrue();
});

test('viewAny denied without permissions', function () {
    $user = phase7UserWithPermissions([]);
    expect($this->policy->viewAny($user)->allowed())->toBeFalse();
});

test('manage allowed with manage permission', function () {
    $user = phase7UserWithPermissions(['dynamic-enums.manage']);
    expect($this->policy->manage($user)->allowed())->toBeTrue();
});

test('manage denied with view-only permission', function () {
    $user = phase7UserWithPermissions(['dynamic-enums.view']);
    expect($this->policy->manage($user)->allowed())->toBeFalse();
});

test('obsolete manageGlobals permission does not grant manage capability', function () {
    $user = phase7UserWithPermissions(['dynamic-enums.manageGlobals']);
    expect($this->policy->manage($user)->allowed())->toBeFalse();
    expect($this->policy->viewAny($user)->allowed())->toBeFalse();
});
