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
 * - Admission revalidated at finalize (not only at start)
 * - Requirement definitions must match Enrollment school
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
        $table->string('code')->nullable();
        $table->string('slug')->nullable();
        $table->string('email')->nullable();
        $table->string('phone_one')->nullable();
        $table->string('phone_two')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
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
        $table->uuid('student_id')->nullable();
        $table->uuid('school_id');
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

    Schema::create('addresses', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id')->nullable();
        $table->uuidMorphs('addressable');
        $table->unsignedBigInteger('country_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->string('address_line_1')->nullable();
        $table->string('address_line_2')->nullable();
        $table->string('landmark')->nullable();
        $table->string('city_text')->nullable();
        $table->string('postal_code')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('countries', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('name');
        $table->string('iso2', 2)->nullable();
        $table->timestamps();
    });

    Schema::create('states', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('cities', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id')->nullable();
        $table->string('name');
        $table->string('label')->nullable();
        $table->string('applies_to');
        $table->text('description')->nullable();
        $table->string('color')->nullable();
        $table->json('options')->nullable();
        $table->timestamps();
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

    // HasAddress contract: country exists + Address.type dynamic enum (global).
    DB::table('countries')->insert([
        'id' => 1,
        'name' => 'Nigeria',
        'iso2' => 'NG',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('dynamic_enums')->insert([
        'id' => (string) Str::uuid(),
        'school_id' => null,
        'name' => 'type',
        'label' => 'Address Type',
        'applies_to' => \App\Models\Address::class,
        'options' => json_encode([
            ['value' => 'residential', 'label' => 'Residential'],
            ['value' => 'postal', 'label' => 'Postal'],
            ['value' => 'billing', 'label' => 'Billing'],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function dropPhase4Schema(): void
{
    foreach ([
        'activity_log',
        'dynamic_enums',
        'cities',
        'states',
        'countries',
        'addresses',
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

function phase4MakeReadyEnrollment(EnrollmentService $service, School $school, User $actor, $session, array $biodata = [], ?string $profileId = null): Enrollment
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

    $enrollment->load('requirementInstances.definition');
    foreach ($enrollment->requirementInstances as $instance) {
        if (($instance->definition?->is_required ?? true) && $instance->isPending()) {
            $service->satisfyRequirement($enrollment, $instance, $actor);
        }
    }

    return $enrollment->fresh(['requirementInstances.definition']);
}

function phase4Admission(School $school, object $session, array $overrides = []): Admission
{
    $admission = new Admission();
    $admission->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Admission::STATUS_ACCEPTED,
    ], $overrides))->save();

    return $admission->fresh();
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

it('reuses existing Profile matched by exact email and fills empty biodata slots only', function () {
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

    // Conservative: established first_name is not overwritten; empty phone is filled.
    expect($profile->id)->toBe($existing->id)
        ->and($profile->first_name)->toBe('Old')
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
        'gender' => null,
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Child',
        'last_name' => 'WithoutEmail',
    ], $profile->id);

    $enrollment = $service->updateBiodata($enrollment, $actor, [
        'first_name' => 'Child',
        'last_name' => 'Updated',
        'gender' => 'female',
        'profile_id' => $profile->id,
    ]);

    $meta = $enrollment->meta;
    unset($meta['biodata']['email']);
    $enrollment->meta = $meta;
    $enrollment->save();

    $final = $service->finalize($enrollment->fresh(), $actor);
    $resolved = Profile::query()->findOrFail($final->student->profile_id);

    // Explicit profile_id allows finalize without email; established last_name is kept,
    // empty gender slot is filled from biodata.
    expect($resolved->id)->toBe($profile->id)
        ->and($resolved->last_name)->toBe('WithoutEmail')
        ->and($resolved->gender)->toBe('female');
});

it('rejects ambiguous identity when multiple profiles share the same email', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);

    phase4Profile(['email' => 'dup@example.com', 'first_name' => 'A', 'last_name' => 'One']);
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

    $meta = $enrollment->meta ?? [];
    $meta['profile_id'] = (string) Str::uuid();
    $enrollment->meta = $meta;
    $enrollment->save();

    try {
        $service->finalize($enrollment->fresh(), $actor);
        expect(false)->toBeTrue();
    } catch (ValidationException $e) {
        $enrollment->refresh();
        expect($enrollment->status)->toBe(Enrollment::STATUS_IN_PROGRESS)
            ->and($enrollment->student_id)->toBeNull()
            ->and(Student::query()->count())->toBe(0);
    }
});

it('reuses explicit profile_id and fills empty biodata slots only', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $profile = phase4Profile([
        'first_name' => 'Before',
        'last_name' => 'Change',
        'email' => 'explicit@example.com',
        'phone' => '111',
        'gender' => null,
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'After',
        'last_name' => 'Change',
        'email' => 'explicit@example.com',
        'phone' => '222',
        'gender' => 'male',
    ], $profile->id);

    $final = $service->finalize($enrollment, $actor);
    $profile->refresh();

    // Explicit profile_id links the enrollment; established non-critical fields are preserved,
    // empty gender is filled.
    expect($final->student->profile_id)->toBe($profile->id)
        ->and($profile->first_name)->toBe('Before')
        ->and($profile->phone)->toBe('111')
        ->and($profile->gender)->toBe('male');
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

// ─── Admission revalidation at finalize ───────────────────────────────────────

it('finalizes successfully when linked Admission remains accepted', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $admission = phase4Admission($school, $session);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'email' => 'from-admission@example.com',
        'first_name' => 'From',
        'last_name' => 'Admission',
    ]);
    $enrollment->admission_id = $admission->id;
    $enrollment->save();

    $final = $service->finalize($enrollment->fresh(), $actor);

    expect($final->status)->toBe(Enrollment::STATUS_ACTIVE)
        ->and($final->admission_id)->toBe($admission->id)
        ->and($final->student_id)->not->toBeNull();

    $admission->refresh();
    expect($admission->student_id)->toBe($final->student_id);
});

it('rejects finalization when Admission is no longer accepted', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $admission = phase4Admission($school, $session);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'email' => 'stale-adm@example.com',
        'first_name' => 'Stale',
        'last_name' => 'Adm',
    ]);
    $enrollment->admission_id = $admission->id;
    $enrollment->save();

    $admission->status = Admission::STATUS_CANCELLED;
    $admission->save();

    $service->finalize($enrollment->fresh(), $actor);
})->throws(ValidationException::class);

it('rejects finalization when Admission school does not match Enrollment school', function () {
    $schoolA = phase4School(['name' => 'School A']);
    $schoolB = phase4School(['name' => 'School B']);
    $actor = phase4User();
    $sessionA = phase4Session($schoolA);
    $sessionB = phase4Session($schoolB);
    $admission = phase4Admission($schoolB, $sessionB);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $schoolA, $actor, $sessionA, [
        'email' => 'cross-adm@example.com',
        'first_name' => 'Cross',
        'last_name' => 'Adm',
    ]);
    DB::table('enrollments')->where('id', $enrollment->id)->update(['admission_id' => $admission->id]);

    $service->finalize($enrollment->fresh(), $actor);
})->throws(ValidationException::class);

it('rejects starting a second Enrollment for the same Admission', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $admission = phase4Admission($school, $session);
    $service = app(EnrollmentService::class);

    $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'admission_id' => $admission->id,
        'biodata' => ['first_name' => 'One', 'last_name' => 'A', 'email' => 'one@example.com'],
    ]);

    $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'admission_id' => $admission->id,
        'biodata' => ['first_name' => 'Two', 'last_name' => 'B', 'email' => 'two@example.com'],
    ]);
})->throws(ValidationException::class);

// ─── Requirement school integrity ────────────────────────────────────────────

it('materializes only same-school requirement definitions', function () {
    $schoolA = phase4School(['name' => 'A']);
    $schoolB = phase4School(['name' => 'B']);
    $actor = phase4User();
    $session = phase4Session($schoolA);

    $defA = new EnrollmentRequirementDefinition();
    $defA->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'code' => 'A_FORM',
        'name' => 'School A Form',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $defB = new EnrollmentRequirementDefinition();
    $defB->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'code' => 'B_FORM',
        'name' => 'School B Form',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $service = app(EnrollmentService::class);
    $enrollment = $service->start($schoolA, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'xy2@example.com'],
    ]);

    expect($enrollment->requirementInstances)->toHaveCount(1)
        ->and($enrollment->requirementInstances->first()->definition_id)->toBe($defA->id);
});

it('rejects satisfying a requirement whose definition belongs to another school', function () {
    $schoolA = phase4School(['name' => 'A']);
    $schoolB = phase4School(['name' => 'B']);
    $actor = phase4User();
    $session = phase4Session($schoolA);
    $service = app(EnrollmentService::class);

    $enrollment = $service->start($schoolA, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'xy3@example.com'],
    ]);

    $defB = new EnrollmentRequirementDefinition();
    $defB->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'code' => 'FOREIGN',
        'name' => 'Foreign Form',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $instanceId = (string) Str::uuid();
    DB::table('enrollment_requirement_instances')->insert([
        'id' => $instanceId,
        'enrollment_id' => $enrollment->id,
        'definition_id' => $defB->id,
        'status' => EnrollmentRequirementInstance::STATUS_PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $instance = EnrollmentRequirementInstance::query()->findOrFail($instanceId);

    $service->satisfyRequirement($enrollment, $instance, $actor);
})->throws(ValidationException::class);

it('model-level guard rejects saving cross-school requirement instance', function () {
    $schoolA = phase4School(['name' => 'A']);
    $schoolB = phase4School(['name' => 'B']);
    $actor = phase4User();
    $session = phase4Session($schoolA);
    $service = app(EnrollmentService::class);

    $enrollment = $service->start($schoolA, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'xy4@example.com'],
    ]);

    $defB = new EnrollmentRequirementDefinition();
    $defB->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'code' => 'FOREIGN2',
        'name' => 'Foreign 2',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $instance = new EnrollmentRequirementInstance();
    $instance->forceFill([
        'id' => (string) Str::uuid(),
        'enrollment_id' => $enrollment->id,
        'definition_id' => $defB->id,
        'status' => EnrollmentRequirementInstance::STATUS_PENDING,
    ])->save();
})->throws(\InvalidArgumentException::class);

it('required unsatisfied requirement blocks finalization', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);

    $def = new EnrollmentRequirementDefinition();
    $def->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'code' => 'MUST',
        'name' => 'Must Form',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $service = app(EnrollmentService::class);
    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => [
            'first_name' => 'Need',
            'last_name' => 'Req',
            'email' => 'needreq@example.com',
        ],
    ]);

    $service->finalize($enrollment, $actor);
})->throws(ValidationException::class);

it('waived required requirement allows finalization', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);

    $def = new EnrollmentRequirementDefinition();
    $def->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'code' => 'WAIVE_ME',
        'name' => 'Waive Me',
        'type' => EnrollmentRequirementDefinition::TYPE_FORM,
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ])->save();

    $service = app(EnrollmentService::class);
    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => [
            'first_name' => 'Waive',
            'last_name' => 'Path',
            'email' => 'waive@example.com',
        ],
    ]);

    $instance = $enrollment->requirementInstances->first();
    $service->waiveRequirement($enrollment, $instance, $actor, 'Document unavailable');

    $final = $service->finalize($enrollment->fresh(), $actor);
    expect($final->status)->toBe(Enrollment::STATUS_ACTIVE);
});

// ─── Transaction / rollback ───────────────────────────────────────────────────

it('rejects invalid Admission before Profile/Student side effects (no partial state)', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $admission = phase4Admission($school, $session);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'email' => 'rollback-adm@example.com',
        'first_name' => 'Roll',
        'last_name' => 'Adm',
    ]);
    $enrollment->admission_id = $admission->id;
    $enrollment->save();

    $admission->status = Admission::STATUS_EXPIRED;
    $admission->save();

    $profilesBefore = Profile::query()->count();
    $studentsBefore = Student::query()->count();

    try {
        $service->finalize($enrollment->fresh(), $actor);
        expect(false)->toBeTrue();
    } catch (ValidationException $e) {
        $enrollment->refresh();
        expect($enrollment->status)->toBe(Enrollment::STATUS_IN_PROGRESS)
            ->and($enrollment->student_id)->toBeNull()
            ->and(Profile::query()->count())->toBe($profilesBefore)
            ->and(Student::query()->count())->toBe($studentsBefore);
    }
});

// ─── Explicit Profile workflow + biodata persistence + identity protection ────

it('supports explicit Profile linking via updateBiodata then finalizes without email', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $profile = phase4Profile([
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
    ]);
    $service = app(EnrollmentService::class);

    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => [
            'first_name' => 'No',
            'last_name' => 'Email',
        ],
    ]);

    $enrollment = $service->updateBiodata($enrollment, $actor, [
        'first_name' => 'No',
        'last_name' => 'Email',
        'profile_id' => $profile->id,
    ]);

    expect($enrollment->meta['profile_id'])->toBe($profile->id);

    $final = $service->finalize($enrollment->fresh(), $actor);
    expect($final->status)->toBe(Enrollment::STATUS_ACTIVE)
        ->and($final->student->profile_id)->toBe($profile->id);
});

it('persists full permanent biodata and address onto Profile on finalization', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $service = app(EnrollmentService::class);

    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Full',
        'middle_name' => 'Data',
        'last_name' => 'Person',
        'email' => 'full.data@example.com',
        'phone' => '+1000111222',
        'date_of_birth' => '2010-05-01',
        'gender' => 'female',
        'title' => 'Miss',
        'address_line_1' => '12 Test Street',
        'address_line_2' => 'Suite 4',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'postal_code' => '100001',
        'country' => 'Nigeria',
    ]);

    $final = $service->finalize($enrollment, $actor);
    $profile = Profile::query()->findOrFail($final->student->profile_id);

    expect($profile->first_name)->toBe('Full')
        ->and($profile->middle_name)->toBe('Data')
        ->and($profile->last_name)->toBe('Person')
        ->and(strtolower((string) $profile->email))->toBe('full.data@example.com')
        ->and($profile->phone)->toBe('+1000111222')
        ->and($profile->gender)->toBe('female')
        ->and($profile->title)->toBe('Miss');

    $addr = $profile->addresses()->where('is_primary', true)->first();
    expect($addr)->not->toBeNull()
        ->and($addr->address_line_1)->toBe('12 Test Street')
        ->and($addr->city_text)->toBe('Lagos')
        ->and($addr->postal_code)->toBe('100001');
});

it('does not silently overwrite established Profile date_of_birth on email match', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $existing = phase4Profile([
        'first_name' => 'Established',
        'last_name' => 'Person',
        'email' => 'established@example.com',
        'date_of_birth' => '2000-01-15',
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Established',
        'last_name' => 'Person',
        'email' => 'established@example.com',
        'date_of_birth' => '2012-12-12',
    ]);

    $service->finalize($enrollment, $actor);
})->throws(ValidationException::class);

it('allows confirmed identity overwrite when staff sets confirm_identity_update', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $existing = phase4Profile([
        'first_name' => 'Established',
        'last_name' => 'Person',
        'email' => 'confirm@example.com',
        'date_of_birth' => '2000-01-15',
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'first_name' => 'Established',
        'last_name' => 'Person',
        'email' => 'confirm@example.com',
        'date_of_birth' => '2012-12-12',
    ]);

    $enrollment = $service->updateBiodata($enrollment, $actor, [
        'email' => 'confirm@example.com',
        'date_of_birth' => '2012-12-12',
        'confirm_identity_update' => true,
    ]);

    $final = $service->finalize($enrollment->fresh(), $actor);
    $profile = Profile::query()->findOrFail($final->student->profile_id);

    expect($profile->id)->toBe($existing->id)
        ->and($profile->date_of_birth?->toDateString())->toBe('2012-12-12');
});

it('rejects silently switching an already-linked profile_id', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $p1 = phase4Profile(['email' => 'p1@example.com']);
    $p2 = phase4Profile(['email' => 'p2@example.com']);
    $service = app(EnrollmentService::class);

    $enrollment = $service->start($school, $actor, [
        'academic_session_id' => $session->id,
        'biodata' => ['first_name' => 'A', 'last_name' => 'B', 'profile_id' => $p1->id],
    ]);

    $service->updateBiodata($enrollment, $actor, [
        'profile_id' => $p2->id,
    ]);
})->throws(ValidationException::class);

it('fills empty Profile slots without overwriting established non-critical phone', function () {
    $school = phase4School();
    $actor = phase4User();
    $session = phase4Session($school);
    $existing = phase4Profile([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada.fill@example.com',
        'phone' => '111',
        'gender' => null,
    ]);

    $service = app(EnrollmentService::class);
    $enrollment = phase4MakeReadyEnrollment($service, $school, $actor, $session, [
        'email' => 'ada.fill@example.com',
        'phone' => '999',
        'gender' => 'female',
    ]);

    $final = $service->finalize($enrollment, $actor);
    $profile = Profile::query()->findOrFail($final->student->profile_id);

    expect($profile->id)->toBe($existing->id)
        ->and($profile->phone)->toBe('111')
        ->and($profile->gender)->toBe('female');
});
