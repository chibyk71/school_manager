<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — operational category authorization and inclusive date filters.
 */

use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildP7AuthSchema();
});

afterEach(function () {
    dropP7AuthSchema();
});

function dropP7AuthSchema(): void
{
    foreach ([
        'enrollments',
        'admissions',
        'student_applications',
        'academic_sessions',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildP7AuthSchema(): void
{
    dropP7AuthSchema();

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('slug')->unique();
        $t->string('code')->nullable();
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('student_applications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->string('status');
        $t->string('application_number')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->timestamp('submitted_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('application_id')->nullable();
        $t->string('status');
        $t->string('admission_number')->nullable();
        $t->timestamp('offered_at')->nullable();
        $t->timestamp('accepted_at')->nullable();
        $t->timestamp('acceptance_deadline')->nullable();
        $t->timestamp('registration_ends_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('admission_id')->nullable();
        $t->string('status');
        $t->timestamp('activated_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
}

function p7aSchool(string $name): School
{
    $s = new School;
    $s->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => Str::upper(Str::random(4)),
    ])->save();

    return $s->fresh();
}

function p7aSession(School $school): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $school->id,
        'name' => '2026/2027',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id];
}

function seedLifecycleMix(School $school, object $session): void
{
    for ($i = 0; $i < 3; $i++) {
        StudentApplication::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'academic_session_id' => $session->id,
            'status' => StudentApplication::STATUS_SUBMITTED,
            'application_number' => 'APP-'.$i,
            'submitted_at' => now()->subHours($i),
        ]);
    }
    for ($i = 0; $i < 2; $i++) {
        Admission::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'academic_session_id' => $session->id,
            'status' => Admission::STATUS_OFFERED,
            'admission_number' => 'ADM-'.$i,
            'offered_at' => now(),
            'acceptance_deadline' => now()->addDays(3),
        ]);
    }
    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_DRAFT,
    ]);
}

it('needsAttention applications-only category excludes admissions and enrollments from items and total', function () {
    $school = p7aSchool('AuthApps');
    $session = p7aSession($school);
    seedLifecycleMix($school, $session);

    $ops = app(LifecycleOperationalService::class);
    $all = $ops->needsAttention($school, 50, null);
    $appsOnly = $ops->needsAttention($school, 50, ['applications']);

    expect($all['total'])->toBeGreaterThan($appsOnly['total']);
    expect($appsOnly['total'])->toBe(3);
    expect(collect($appsOnly['items'])->every(fn ($i) => str_starts_with($i['type'], 'application_')))->toBeTrue();
    expect(collect($appsOnly['items'])->contains(fn ($i) => str_starts_with($i['type'], 'offer_') || str_starts_with($i['type'], 'enrollment_')))->toBeFalse();
});

it('needsAttention admissions-only category returns only admission items and totals', function () {
    $school = p7aSchool('AuthAdm');
    $session = p7aSession($school);
    seedLifecycleMix($school, $session);

    $ops = app(LifecycleOperationalService::class);
    $admOnly = $ops->needsAttention($school, 50, ['admissions']);

    expect($admOnly['total'])->toBe(2);
    expect(collect($admOnly['items'])->every(fn ($i) => in_array($i['type'], [
        'offer_awaiting_acceptance',
        'accepted_awaiting_registration',
    ], true) || str_starts_with($i['type'], 'offer_')))->toBeTrue();
});

it('needsAttention enrollments-only category returns only enrollment items and totals', function () {
    $school = p7aSchool('AuthEnr');
    $session = p7aSession($school);
    seedLifecycleMix($school, $session);

    $ops = app(LifecycleOperationalService::class);
    $enrOnly = $ops->needsAttention($school, 50, ['enrollments']);

    expect($enrOnly['total'])->toBe(1);
    expect(collect($enrOnly['items'])->every(fn ($i) => str_starts_with($i['type'], 'enrollment_')))->toBeTrue();
});

it('date_to inclusive end-of-day includes records later on the selected day', function () {
    $school = p7aSchool('DateBoundary');
    $session = p7aSession($school);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => '2026-03-15 23:45:00',
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => '2026-03-16 00:15:00',
    ]);

    $ops = app(LifecycleOperationalService::class);

    $withInclusive = $ops->applicationsQuery($school, [
        'date_from' => '2026-03-15',
        'date_to' => '2026-03-15',
    ])->count();

    expect($withInclusive)->toBe(1);

    $normalized = $ops->normalizeReportFilters([
        'date_from' => '2026-03-15',
        'date_to' => '2026-03-15',
    ]);
    expect($normalized['date_from'])->toStartWith('2026-03-15 00:00:00');
    expect($normalized['date_to'])->toStartWith('2026-03-15 23:59:59');
});

it('deadline_to inclusive includes deadlines later on the selected day', function () {
    $school = p7aSchool('DeadlineBoundary');
    $session = p7aSession($school);

    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
        'acceptance_deadline' => '2026-04-01 18:30:00',
    ]);
    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
        'acceptance_deadline' => '2026-04-02 01:00:00',
    ]);

    $ops = app(LifecycleOperationalService::class);
    $count = $ops->admissionsQuery($school, [
        'deadline_from' => '2026-04-01',
        'deadline_to' => '2026-04-01',
    ])->count();

    expect($count)->toBe(1);
});
