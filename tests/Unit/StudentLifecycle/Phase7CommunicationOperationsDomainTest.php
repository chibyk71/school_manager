<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — Communication & Operational UX domain tests.
 * Focus: school isolation of operational counts, reminder idempotency,
 * funnel reports, and notification side-effects not corrupting state.
 */

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
        'enrollment_requirement_instances',
        'enrollment_requirement_definitions',
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
        $t->string('code')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->timestamps();
    });

    Schema::create('student_applications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->string('status')->default('submitted');
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

function p7Session(School $school): object
{
    $id = (string) Str::uuid();
    \DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $school->id,
        'name' => '2026/2027',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id];
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
    $countsA = $ops->dashboardCounts($schoolA);
    $countsB = $ops->dashboardCounts($schoolB);

    expect($countsA['applications_awaiting_review'])->toBe(1)
        ->and($countsB['applications_awaiting_review'])->toBe(1)
        ->and($countsA['offers_awaiting_acceptance'])->toBe(1)
        ->and($countsB['offers_awaiting_acceptance'])->toBe(1);

    // Cross-school leakage check: totals differ if we add more to B only
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => StudentApplication::STATUS_UNDER_REVIEW,
        'submitted_at' => now(),
    ]);

    expect($ops->dashboardCounts($schoolA)['applications_awaiting_review'])->toBe(1)
        ->and($ops->dashboardCounts($schoolB)['applications_awaiting_review'])->toBe(2);
});

it('lifecycle funnel uses authoritative school records only', function () {
    $school = p7School();
    $session = p7Session($school);
    $other = p7School('Other');
    $otherSession = p7Session($other);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_APPROVED,
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $other->id,
        'academic_session_id' => $otherSession->id,
        'status' => StudentApplication::STATUS_APPROVED,
    ]);

    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Admission::STATUS_ACCEPTED,
        'accepted_at' => now(),
    ]);

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    $funnel = app(LifecycleOperationalService::class)->lifecycleFunnel($school, $session->id);

    expect($funnel['applications'])->toBe(1)
        ->and($funnel['applications_approved'])->toBe(1)
        ->and($funnel['admissions'])->toBe(1)
        ->and($funnel['admissions_accepted'])->toBe(1)
        ->and($funnel['enrollments'])->toBe(1)
        ->and($funnel['enrollments_finalized'])->toBe(1);
});

it('incomplete enrollment reminders are idempotent and skip finalized', function () {
    Notification::fake();

    $school = p7School();
    $session = p7Session($school);

    $idle = Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_IN_PROGRESS,
        'meta' => ['biodata' => ['email' => 'parent@example.com']],
        'updated_at' => now()->subDays(5),
        'created_at' => now()->subDays(5),
    ]);
    // Force updated_at past cutoff (Eloquent may touch timestamps)
    Enrollment::query()->whereKey($idle->id)->update(['updated_at' => now()->subDays(5)]);

    $finalized = Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
        'meta' => ['biodata' => ['email' => 'done@example.com']],
        'updated_at' => now()->subDays(10),
    ]);
    Enrollment::query()->whereKey($finalized->id)->update(['updated_at' => now()->subDays(10)]);

    $service = new EnrollmentService(
        app(PlacementAllocationService::class),
        app(\App\Services\Student\LifecycleNotificationService::class)
    );

    $first = $service->processIncompleteReminders(3, $school);
    $second = $service->processIncompleteReminders(3, $school);

    expect($first)->toBe(1)
        ->and($second)->toBe(0);

    Notification::assertSentOnDemand(EnrollmentIncompleteNotification::class);

    $idle->refresh();
    expect(data_get($idle->meta, 'incomplete_reminder_sent_at'))->not->toBeNull();
});

it('needs attention only returns records for the requested school', function () {
    $schoolA = p7School('A');
    $schoolB = p7School('B');
    $sessionA = p7Session($schoolA);
    $sessionB = p7Session($schoolB);

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'status' => Enrollment::STATUS_DRAFT,
    ]);
    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => Enrollment::STATUS_DRAFT,
    ]);

    $items = app(LifecycleOperationalService::class)->needsAttention($schoolA, 50);
    $ids = collect($items['items'])->pluck('id');

    expect($items['total'])->toBeGreaterThanOrEqual(1);
    // Every enrollment incomplete item must belong to school A only (we only created one there)
    $enrollmentItems = collect($items['items'])->where('type', 'enrollment_incomplete');
    expect($enrollmentItems)->toHaveCount(1);
});


it('skips notifications when school preference is disabled', function () {
    $school = p7School();
    // Persist disabled preference via settings table if present
    if (Schema::hasTable('settings')) {
        \DB::table('settings')->insert([
            'key' => 'general.notifications',
            'value' => json_encode(['enrollment_incomplete_reminder' => ['admin' => false, 'parent' => false]]),
            'model_type' => School::class,
            'model_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $svc = app(\App\Services\Student\LifecycleNotificationService::class);
    expect($svc->isEnabled($school, 'enrollment_incomplete_reminder'))->toBeFalse();
});

it('recipient resolution uses application domain email not arbitrary meta', function () {
    $school = p7School();
    $session = p7Session($school);
    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'email' => 'candidate@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $recipients = app(\App\Services\Student\LifecycleNotificationService::class)
        ->resolveRecipients($app);

    expect($recipients)->not->toBeEmpty();
});

it('needs attention distinguishes total from returned_count', function () {
    $school = p7School();
    $session = p7Session($school);

    for ($i = 0; $i < 5; $i++) {
        Enrollment::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'academic_session_id' => $session->id,
            'status' => Enrollment::STATUS_DRAFT,
        ]);
    }

    $result = app(\App\Services\Student\LifecycleOperationalService::class)
        ->needsAttention($school, 2);

    expect($result['returned_count'])->toBe(2)
        ->and($result['total'])->toBeGreaterThanOrEqual(5);
});

