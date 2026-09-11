<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — Communication & Operational UX domain tests.
 * Focus: school isolation of operational counts, reminder idempotency,
 * funnel reports, and notification side-effects not corrupting state.
 */

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Notifications\Student\EnrollmentIncompleteNotification;
use App\Services\Student\EnrollmentService;
use App\Services\Student\LifecycleOperationalService;
use App\Services\Student\PlacementAllocationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase7Schema();
});

afterEach(function () {
    dropPhase7Schema();
});

function dropPhase7Schema(): void
{
    foreach ([
        'notification_logs',
        'enrollment_requirement_instances',
        'enrollment_requirement_definitions',
        'student_session_placements',
        'class_sections',
        'students',
        'enrollments',
        'admissions',
        'student_applications',
        'academic_sessions',
        'settings',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildPhase7Schema(): void
{
    dropPhase7Schema();

    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('key');
        $t->json('value')->nullable();
        $t->nullableUuidMorphs('model');
        $t->timestamps();
    });

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('slug')->unique();
        $t->string('code')->nullable();
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('notification_logs', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('notifiable_type');
        $t->uuid('notifiable_id'); // uuid-safe for AnonymousNotifiable/context
        $t->string('notification_type');
        $t->unsignedBigInteger('notification_id')->default(0);
        $t->string('channel');
        $t->string('provider')->nullable();
        $t->string('recipient');
        $t->text('message')->nullable();
        $t->string('sender')->nullable();
        $t->boolean('success')->default(false);
        $t->text('error')->nullable();
        $t->unsignedInteger('segments')->default(1);
        $t->decimal('cost', 10, 4)->default(0);
        $t->json('metadata')->nullable();
        $t->timestamp('delivered_at')->nullable();
        $t->timestamps();
    });

    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->string('state', 20)->default('draft');
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('student_applications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->string('status')->default('submitted');
        $t->string('application_number')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('email')->nullable();
        $t->string('source')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->string('fee_payment_status')->nullable();
        $t->timestamp('submitted_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('application_id')->nullable();
        $t->string('status')->default('offered');
        $t->string('admission_number')->nullable();
        $t->timestamp('acceptance_deadline')->nullable();
        $t->timestamp('registration_ends_at')->nullable();
        $t->timestamp('offered_at')->nullable();
        $t->timestamp('accepted_at')->nullable();
        $t->timestamp('reminder_sent_at')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('admission_id')->nullable();
        $t->uuid('student_id')->nullable();
        $t->string('status')->default('draft');
        $t->timestamp('activated_at')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('class_sections', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name')->nullable();
        $t->unsignedInteger('capacity')->default(0);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('status')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id')->nullable();
        $t->uuid('student_id');
        $t->uuid('academic_session_id');
        $t->uuid('class_section_id')->nullable();
        $t->boolean('is_current')->default(true);
        $t->timestamps();
    });

    Schema::create('enrollment_requirement_definitions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('code');
        $t->string('name');
        $t->string('type')->default('document');
        $t->boolean('is_required')->default(true);
        $t->boolean('is_active')->default(true);
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('enrollment_requirement_instances', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('enrollment_id');
        $t->uuid('definition_id');
        $t->string('status')->default('pending');
        $t->timestamp('satisfied_at')->nullable();
        $t->timestamp('waived_at')->nullable();
        $t->timestamps();
    });
}

function p7School(string $name = 'School A'): School
{
    return School::query()->create([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)).rand(10, 99),
    ]);
}

function p7Session(School $school): AcademicSession
{
    $session = new AcademicSession;
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => '2026/2027',
        'state' => 'active',
    ])->save();

    return $session->fresh();
}

it('dashboard counts are school-scoped', function () {
    $schoolA = p7School('Alpha');
    $schoolB = p7School('Beta');
    $sessionA = p7Session($schoolA);
    $sessionB = p7Session($schoolB);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'status' => Admission::STATUS_OFFERED,
        'acceptance_deadline' => now()->addDays(2),
        'offered_at' => now(),
    ]);
    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => Admission::STATUS_OFFERED,
        'acceptance_deadline' => now()->addDays(2),
        'offered_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $countsA = $ops->dashboardCounts($schoolA, $sessionA);
    $countsB = $ops->dashboardCounts($schoolB, $sessionB);

    expect($countsA['applications_awaiting_review'])->toBe(1)
        ->and($countsB['applications_awaiting_review'])->toBe(1)
        ->and($countsA['offers_awaiting_acceptance'])->toBe(1)
        ->and($countsB['offers_awaiting_acceptance'])->toBe(1);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => StudentApplication::STATUS_UNDER_REVIEW,
        'submitted_at' => now(),
    ]);

    expect($ops->dashboardCounts($schoolA, $sessionA)['applications_awaiting_review'])->toBe(1)
        ->and($ops->dashboardCounts($schoolB, $sessionB)['applications_awaiting_review'])->toBe(2);
});
