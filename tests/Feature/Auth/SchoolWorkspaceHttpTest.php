<?php

use App\Enums\Auth\AccountLifecycle;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Services\SchoolService;
use Illuminate\Support\Str;

/**
 * HTTP behavioural coverage for SchoolContext middleware and SchoolService workspace rules.
 * Membership-only School access (no Tenant-wide workspace permission in this phase).
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

test('fresh school service invalidates section when switching school from session only', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user->schools()->attach([$schoolA->id, $schoolB->id]);
    $sectionA = SchoolSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'name' => 'Section A',
    ]);

    $this->actingAs($user);
    session([
        'active_school_id' => $schoolA->id,
        'active_section_id' => $sectionA->id,
    ]);

    $service = app()->make(SchoolService::class);
    $service->setActiveSchool($schoolB);

    expect(session('active_section_id'))->toBeNull();
    expect($service->getActiveSection())->toBeNull();
});

test('section header without authorized school yields no active section', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $school = School::factory()->create();
    $section = SchoolSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'Orphan Section',
    ]);

    $this->actingAs($user);
    $service = app()->make(SchoolService::class);
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('X-Section-Id', $section->id);

    expect($service->getActiveSection($request))->toBeNull();
});

test('cross-school section header is rejected', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user->schools()->attach($schoolA->id);
    $sectionB = SchoolSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'name' => 'Foreign Section',
    ]);

    $this->actingAs($user);
    $service = app()->make(SchoolService::class);
    $service->setActiveSchool($schoolA);

    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('X-Section-Id', $sectionB->id);

    expect($service->getActiveSection($request))->toBeNull();
    expect(session('active_section_id'))->toBeNull();
});

test('valid section header under active school is accepted', function () {
    $user = User::factory()->create(['lifecycle' => AccountLifecycle::Active]);
    $school = School::factory()->create();
    $user->schools()->attach($school->id);
    $section = SchoolSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'Valid Section',
    ]);

    $this->actingAs($user);
    $service = app()->make(SchoolService::class);
    $service->setActiveSchool($school);

    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('X-Section-Id', $section->id);

    $resolved = $service->getActiveSection($request);
    expect($resolved)->not->toBeNull();
    expect($resolved->is($section))->toBeTrue();
});
