<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolService;

/**
 * HTTP behavioural coverage for SchoolContext middleware.
 * Membership-only School access. Section context is not part of School activation.
 */

test('member can bind participating school via header', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $school = School::factory()->create();
    $user->schools()->attach($school->id);

    $this->actingAs($user)
        ->withHeaders(['X-School-Id' => $school->id])
        ->get('/dashboard');

    expect(session('active_school_id'))->toBe($school->id);
});

test('non-member explicit school selection returns 403', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $school = School::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-School-Id' => $school->id])
        ->get('/dashboard');

    $response->assertForbidden();
    expect(session('active_school_id'))->toBeNull();
});

test('unauthorized stale session school context is cleared', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $school = School::factory()->create();

    $this->actingAs($user);
    session(['active_school_id' => $school->id]);

    $this->actingAs($user)->get('/dashboard');

    expect(session('active_school_id'))->toBeNull();
});

test('school switch works without section context', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user->schools()->attach([$schoolA->id, $schoolB->id]);

    $this->actingAs($user);
    $service = app()->make(SchoolService::class);
    $service->setActiveSchool($schoolA);
    expect(session('active_school_id'))->toBe($schoolA->id);

    $service->setActiveSchool($schoolB);
    expect(session('active_school_id'))->toBe($schoolB->id);
    expect(method_exists($service, 'getActiveSection'))->toBeFalse();
    expect(method_exists($service, 'setActiveSection'))->toBeFalse();
});
