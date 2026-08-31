<?php

uses(Tests\TestCase::class);

/**
 * Phase 4 Enrollment domain tests — focused schema (SQLite-friendly).
 *
 * Covers:
 * - biodata persisted to Profile after finalization
 * - existing Profile reuse by email
 * - candidates without email require explicit profile_id
 * - ambiguous identity (multiple email matches) blocked
 * - same Profile, different schools → separate Student capacity
 * - concurrent/duplicate Student protection (unique school+profile)
 * - rollback if finalization fails mid-flight
 */

use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\EnrollmentRequirementDefinition;
use App\Models\Student\EnrollmentRequirementInstance;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Student\EnrollmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase4Schema();
});

afterEach(function () {
    dropPhase4Schema();
});

function buildPhase4Schema(): void
{
    dropPhase4Schema();

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('title')->nullable();
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('gender')->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->text('notes')->nullable();
        $table->uuid('user_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->boolean('is_current')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('application_id')->nullable();
        $table->uuid('student_id')->nullable();
        $table->uuid('class_level_id')->nullable();
        $table->uuid('academic_session_id')->nullable();
        $table->string('status', 40)->default('offered');
        $table->json('configs')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('students', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('profile_id');
        $table->string('status', 50)->default('active');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['school_id', 'profile_id'], 'uq_students_school_profile');
    });

    Schema::create('enrollments', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('student_id')->nullable();
        $table->uuid('academic_session_id');
        $table->uuid('admission_id')->nullable()->unique();
        $table->string('status', 40)->default('draft');
        $table->timestamp('started_at')->nullable();
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('withdrawn_at')->nullable();
        $table->timestamp('transferred_out_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->text('notes')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('enrollment_requirement_definitions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('code');
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('type', 40);
        $table->boolean('is_required')->default(true);
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->json('config')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('enrollment_requirement_instances', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('enrollment_id');
        $table->uuid('definition_id');
        $table->string('status', 20)->default('pending');
        $table->timestamp('satisfied_at')->nullable();
        $table->uuid('satisfied_by')->nullable();
        $table->timestamp('waived_at')->nullable();
        $table->uuid('waived_by')->nullable();
        $table->text('waiver_reason')->nullable();
        $table->uuid('document_id')->nullable();
        $table->string('external_reference')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
        $table->unique(['enrollment_id', 'definition_id']);
    });

    Schema::create('activity_log', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
        $table->nullableUuidMorphs('subject');
        $table->nullableUuidMorphs('causer');
        $table->json('properties')->nullable();
        $table->timestamps();
    });
}

function dropPhase4Schema(): void
{
    foreach ([
        'activity_log',
        'enrollment_requirement_instances',
        'enrollment_requirement_definitions',
        'enrollments',
        'students',
        'admissions',
        'academic_sessions',
        'profiles',
        'users',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function phase4School(array $overrides = []): School
{
    $school = new School();
    $school->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Test School',
    ], $overrides))->save();

    return $school->fresh();
}

function phase4User(array $overrides = []): User
{
    $user = new User();
    $user->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Staff User',
        'email' => 'staff@example.com',
    ], $overrides))->save();

    return $user->fresh();
}

function phase4Session(School $school, array $overrides = []): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert(array_merge([
        'id' => $id,
        'school_id' => $school->id,
        'name' => '2026/2027',
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return (object) ['id' => $id, 'school_id' => $school->id];
}

function phase4Profile(array $attrs = []): Profile
{
    $profile = new Profile();
    $profile->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ], $attrs))->save();

    return $profile->fresh();
}

function phase4MakeReadyEnrollment(EnrollmentService $service, School $school, User $actor, array $session, array $biodata = [], ?string $profileId = null): Enrollment
{
    $data = [
        'academic_session_id' => $session->id,
        'biodata' => array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
        ], $biodata),
    ];
    if ($profileId) {
        $data['profile_id'] = $profileId;
    }

    $enrollment = $service->start($school, $actor, $data);

    // Satisfy any required instances so readiness is not blocked by requirements.
    $enrollment->load('requirementInstances.definition');
    foreach ($enrollment->requirementInstances as $instance) {
        if (($instance->definition?->is_required ?? true) && $instance->isPending()) {
            $service->satisfyRequirement($enrollment, $instance, $actor);
        }
    }

    return $enrollment->fresh(['requirementInstances.definition']);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists biodata to Profile on finalization (not only Enrollment.meta)', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
        'phone' => '+15551234',
        'gender' => 'female',
    ]);

    $final = $service->finalize($enrollment, $actor);

    expect($final->status)->toBe(Enrollment::STATUS_ACTIVE)
        ->and($final->student_id)->not->toBeNull();

    $profile = Profile::query()->findOrFail($final->student->profile_id);
    expect($profile->first_name)->toBe('Grace')
        ->and($profile->last_name)->toBe('Hopper')
        ->and(strtolower((string) $profile->email))->toBe('grace@example.com')
        ->and($profile->phone)->toBe('+15551234')
        ->and($profile->gender)->toBe('female');
});

it('reuses existing Profile matched by exact email and updates biodata on it', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $existing = phase4Profile([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'reuse@example.com',
        'phone' => null,
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'reuse@example.com',
        'phone' => '999',
    ]);

    $final = $service->finalize($enrollment, $actor);
    $profile = Profile::query()->findOrFail($final->student->profile_id);

    expect($profile->id)->toBe($existing->id)
        ->and($profile->first_name)->toBe('New')
        ->and($profile->phone)->toBe('999')
        ->and(Profile::query()->whereRaw('LOWER(email) = ?', ['reuse@example.com'])->count())->toBe(1);
});

it('blocks finalization without email or explicit profile_id', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $service = app(EnrollmentService::class);

    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => [
            'first_name' => 'NoEmail',
            'last_name' => 'Kid',
        ],
    ]);

    $readiness = $service->evaluateReadiness($enrollment);
    expect($readiness['ready'])->toBeFalse();

    $service->finalize($enrollment, $actor);
})->throws(ValidationException::class);

it('allows finalization without email when staff supplies explicit profile_id', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $profile = phase4Profile([
        'first_name' => 'Child',
        'last_name' => 'WithoutEmail',
        'email' => null,
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Child',
        'last_name' => 'WithoutEmail',
        // intentionally no email
    ], $profile->id);

    // strip email from biodata if sanitize left nothing
    $enrollment = $service->updateBiodata($enrollment, $actor, [
        'first_name' => 'Child',
        'last_name' => 'Updated',
        'profile_id' => $profile->id,
    ]);

    // Ensure no email on meta biodata
    $meta = $enrollment->meta;
    unset($meta['biodata']['email']);
    $enrollment->meta = $meta;
    $enrollment->save();

    $final = $service->finalize($enrollment->fresh(), $actor);
    $resolved = Profile::query()->findOrFail($final->student->profile_id);

    expect($resolved->id)->toBe($profile->id)
        ->and($resolved->last_name)->toBe('Updated');
});

it('rejects ambiguous identity when multiple profiles share the same email', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);

    phase4Profile(['email' => 'dup@example.com', 'first_name' => 'A', 'last_name' => 'One']);
    // Second profile with same email (data anomaly)
    phase4Profile(['email' => 'dup@example.com', 'first_name' => 'B', 'last_name' => 'Two']);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'A',
        'last_name' => 'One',
        'email' => 'dup@example.com',
    ]);

    $service->finalize($enrollment, $actor);
})->throws(ValidationException::class);

it('creates separate Student capacity for same Profile in different schools', function () {
    $schoolA = phase4School(['name' => 'School A']);
    $schoolB = phase4School(['name' => 'School B']);
    $actor = phase4User();
    $sessionA = phase4Session($schoolA);
    $sessionB = phase4Session($schoolB);
    $service = app(EnrollmentService::class);

    $enA = phase4MakeReadyEnrollment($service, $schoolA, $actor, $sessionA, [
        'email' => 'multi@example.com',
        'first_name' => 'Multi',
        'last_name' => 'School',
    ]);
    $finalA = $service->finalize($enA, $actor);

    $enB = phase4MakeReadyEnrollment($service, $schoolB, $actor, $sessionB, [
        'email' => 'multi@example.com',
        'first_name' => 'Multi',
        'last_name' => 'School',
    ]);
    $finalB = $service->finalize($enB, $actor);

    expect($finalA->student->profile_id)->toBe($finalB->student->profile_id)
        ->and($finalA->student_id)->not->toBe($finalB->student_id)
        ->and($finalA->student->school_id)->toBe($schoolA->id)
        ->and($finalB->student->school_id)->toBe($schoolB->id)
        ->and(Student::query()->where('profile_id', $finalA->student->profile_id)->count())->toBe(2);
});

it('reuses existing Student capacity within the same school (no duplicate Student)', function () {
    $school = phase4School();
    $actor = phase4User();
    $session1 = phase4Session($school, ['name' => 'Year 1']);
    $session2Id = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $session2Id,
        'school_id' => $school->id,
        'name' => 'Year 2',
        'is_current' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $session2 = (object) ['id' => $session2Id, 'school_id' => $school->id];

    $service = app(EnrollmentService::class);

    $en1 = phase4MakeReadyEnrollment($service, $school, $actor, $session1, [
        'email' => 'same@example.com',
        'first_name' => 'Same',
        'last_name' => 'Person',
    ]);
    $final1 = $service->finalize($en1, $actor);

    // Complete first enrollment so second session does not conflict on active status.
    $final1->status = Enrollment::STATUS_COMPLETED;
    $final1->completed_at = now();
    $final1->save();

    $en2 = phase4MakeReadyEnrollment($service, $school, $actor, $session2, [
        'email' => 'same@example.com',
        'first_name' => 'Same',
        'last_name' => 'Person',
    ]);
    $final2 = $service->finalize($en2, $actor);

    expect($final2->student_id)->toBe($final1->student_id)
        ->and(Student::query()->where('school_id', $school->id)->where('profile_id', $final1->student->profile_id)->count())->toBe(1);
});

it('enforces unique school+profile at the database layer', function () {
    $school = phase4School();
    $profile = phase4Profile();

    $s1 = new Student();
    $s1->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'profile_id' => $profile->id,
        'status' => 'active',
    ])->save();

    $s2 = new Student();
    $s2->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'profile_id' => $profile->id,
        'status' => 'active',
    ])->save();
})->throws(\Illuminate\Database\QueryException::class);

it('rolls back enrollment activation if student link cannot be established', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'email' => 'rollback@example.com',
        'first_name' => 'Roll',
        'last_name' => 'Back',
    ]);

    // Force failure after readiness by pointing profile_id at a non-existent id mid-flight.
    $meta = $enrollment->meta ?? [];
    $meta['profile_id'] = (string) Str::uuid(); // does not exist
    $enrollment->meta = $meta;
    $enrollment->save();

    try {
        $service->finalize($enrollment->fresh(), $actor);
        expect(false)->toBeTrue(); // should not reach
    } catch (ValidationException $e) {
        $enrollment->refresh();
        expect($enrollment->status)->toBe(Enrollment::STATUS_IN_PROGRESS)
            ->and($enrollment->student_id)->toBeNull()
            ->and(Student::query()->count())->toBe(0);
    }
});

it('updates Profile biodata when reusing explicit profile_id', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $profile = phase4Profile([
        'first_name' => 'Before',
        'last_name' => 'Change',
        'email' => 'explicit@example.com',
        'phone' => '111',
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'After',
        'last_name' => 'Change',
        'email' => 'explicit@example.com',
        'phone' => '222',
    ], $profile->id);

    $final = $service->finalize($enrollment, $actor);
    $profile->refresh();

    expect($final->student->profile_id)->toBe($profile->id)
        ->and($profile->first_name)->toBe('After')
        ->and($profile->phone)->toBe('222');
});

it('materializes school requirement instances on start', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);

    $def = new EnrollmentRequirementDefinition();
    $def->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'code' => 'FORM_A',
        'name' => 'Form A',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $service = app(EnrollmentService::class);
    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'xy@example.com'],
    ]);

    expect($enrollment->requirementInstances)->toHaveCount(1)
        ->and($enrollment->requirementInstances->first()->status)->toBe(EnrollmentRequirementInstance::STATUS_PENDING);
});
