<?php

uses(Tests\TestCase::class);

/**
 * Phase 3 – Admission domain unit tests.
 *
 * Uses a minimal schema (same approach as Phase 1 / Phase 2) so tests run
 * independently of unrelated full-suite sqlite migration failures.
 */

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\Student\Admission;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Services\Student\AdmissionService;
use App\Services\Student\StudentApplicationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase3Schema();
});

afterEach(function () {
    dropPhase3Schema();
});

function buildPhase3Schema(): void
{
    dropPhase3Schema();

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
        $table->string('slug')->nullable();
        $table->string('email')->nullable();
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // Phase 7 notification path (AdmissionService → LifecycleNotificationService → getMergedSettings)
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key');
        $table->json('value')->nullable();
        $table->nullableUuidMorphs('model');
        $table->timestamps();
    });

    Schema::create('notification_logs', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id')->nullable();
        $table->string('notifiable_type');
        $table->uuid('notifiable_id');
        $table->string('notification_type');
        $table->unsignedBigInteger('notification_id')->default(0);
        $table->string('channel');
        $table->string('provider')->nullable();
        $table->string('recipient');
        $table->text('message')->nullable();
        $table->boolean('success')->default(false);
        $table->text('error')->nullable();
        $table->unsignedInteger('segments')->default(1);
        $table->json('metadata')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->string('short_code')->nullable();
        $table->text('description')->nullable();
        $table->string('source')->default('custom');
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('class_levels', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_section_id');
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->integer('sequence')->default(1);
        $table->boolean('is_active')->default(true);
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

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id')->nullable();
        $table->string('name');
        $table->string('applies_to')->nullable();
        $table->json('options')->nullable();
        $table->timestamps();
    });

    Schema::create('student_applications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->uuid('class_level_id')->nullable();
        $table->uuid('school_section_id')->nullable();
        $table->string('application_number')->nullable();
        $table->string('status')->default('submitted');
        $table->string('source')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('application_token', 100)->nullable();
        $table->unsignedBigInteger('reviewed_by')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('admin_notes')->nullable();
        $table->uuid('student_id')->nullable();
        $table->string('fee_payment_status', 30)->default('not_required');
        $table->json('guardians_data')->nullable();
        $table->json('custom_data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('student_id')->nullable();
        $table->uuid('application_id')->nullable();
        $table->uuid('class_level_id')->nullable();
        $table->uuid('school_section_id')->nullable();
        $table->uuid('academic_session_id')->nullable();
        $table->string('roll_no')->nullable();
        $table->string('status')->default('offered');
        $table->timestamp('offered_at')->nullable();
        $table->timestamp('acceptance_deadline')->nullable();
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('declined_at')->nullable();
        $table->timestamp('expired_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->date('registration_date')->nullable();
        $table->timestamp('registration_starts_at')->nullable();
        $table->timestamp('registration_ends_at')->nullable();
        $table->timestamp('reminder_sent_at')->nullable();
        $table->text('notes')->nullable();
        $table->json('configs')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

function dropPhase3Schema(): void
{
    Schema::dropIfExists('notification_logs');
    Schema::dropIfExists('admissions');
    Schema::dropIfExists('student_applications');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('class_levels');
    Schema::dropIfExists('school_sections');
    Schema::dropIfExists('users');
    Schema::dropIfExists('settings');
    Schema::dropIfExists('schools');
}

function p3Uuid(): string
{
    return (string) Str::uuid();
}

function p3School(string $name = 'School A'): School
{
    $school = new School();
    $school->forceFill([
        'id' => p3Uuid(),
        'name' => $name,
        'code' => strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 6)),
        'slug' => Str::slug($name).'-'.Str::random(4),
    ]);
    $school->save();

    return $school;
}

function p3Section(School $school): SchoolSection
{
    $section = new SchoolSection();
    $section->forceFill([
        'id' => p3Uuid(),
        'school_id' => $school->id,
        'name' => 'Primary',
        'short_code' => 'PRI',
        'is_active' => true,
    ]);
    $section->save();

    return $section;
}

function p3ClassLevel(School $school, ?SchoolSection $section = null): ClassLevel
{
    $section = $section ?? p3Section($school);
    $level = new ClassLevel();
    $level->forceFill([
        'id' => p3Uuid(),
        'school_section_id' => $section->id,
        'name' => 'Primary 1',
        'sequence' => 1,
        'is_active' => true,
    ]);
    $level->save();

    return $level;
}

function p3Session(School $school, string $name = '2026/2027'): AcademicSession
{
    $session = new AcademicSession();
    $session->forceFill([
        'id' => p3Uuid(),
        'school_id' => $school->id,
        'name' => $name,
        'is_current' => true,
    ]);
    $session->save();

    return $session;
}

function p3User(): User
{
    $user = new User();
    $user->forceFill([
        'name' => 'Staff',
        'email' => 'staff-'.Str::random(6).'@example.test',
    ]);
    $user->save();

    return $user;
}

function p3ApprovedApplication(School $school, ClassLevel $level, AcademicSession $session, array $overrides = []): StudentApplication
{
    $app = new StudentApplication();
    $app->forceFill(array_merge([
        'id' => p3Uuid(),
        'school_id' => $school->id,
        'class_level_id' => $level->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_APPROVED,
        'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'application_number' => 'APP-'.Str::upper(Str::random(6)),
    ], $overrides));
    $app->save();

    return $app;
}

function p3Admission(School $school, ClassLevel $level, AcademicSession $session, array $overrides = []): Admission
{
    $admission = new Admission();
    $admission->forceFill(array_merge([
        'id' => p3Uuid(),
        'school_id' => $school->id,
        'class_level_id' => $level->id,
        'academic_session_id' => $session->id,
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
        'acceptance_deadline' => now()->addDays(7),
    ], $overrides));
    $admission->save();

    return $admission;
}

function p3Service(?StudentApplicationService $appService = null): AdmissionService
{
    return new AdmissionService($appService ?? app(StudentApplicationService::class));
}

it('allows admission without application or student', function () {
    $school = p3School();
    $level = p3ClassLevel($school);
    $session = p3Session($school);

    $admission = p3Admission($school, $level, $session, [
        'application_id' => null,
        'student_id' => null,
    ]);

    expect($admission->application_id)->toBeNull()
        ->and($admission->student_id)->toBeNull()
        ->and($admission->status)->toBe(Admission::STATUS_OFFERED);
});

it('allows valid status transitions from offered', function () {
    $school = p3School();
    $level = p3ClassLevel($school);
    $session = p3Session($school);
    $admission = p3Admission($school, $level, $session);

    expect($admission->canTransitionTo(Admission::STATUS_ACCEPTED))->toBeTrue()
        ->and($admission->canTransitionTo(Admission::STATUS_DECLINED))->toBeTrue()
        ->and($admission->canTransitionTo(Admission::STATUS_EXPIRED))->toBeTrue()
        ->and($admission->canTransitionTo(Admission::STATUS_CANCELLED))->toBeTrue();
});

it('rejects invalid status transitions from accepted', function () {
    $school = p3School();
    $level = p3ClassLevel($school);
    $session = p3Session($school);
    $admission = p3Admission($school, $level, $session, [
        'status' => Admission::STATUS_ACCEPTED,
        'accepted_at' => now(),
    ]);

    expect($admission->canTransitionTo(Admission::STATUS_OFFERED))->toBeFalse();
    expect(fn () => $admission->transitionTo(Admission::STATUS_DECLINED))
        ->toThrow(ValidationException::class);
});
