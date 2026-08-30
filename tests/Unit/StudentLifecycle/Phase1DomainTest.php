<?php

uses(Tests\TestCase::class);

/**
 * Phase 1 – Student Lifecycle domain foundation tests.
 *
 * Runs independently of the full migration suite (known unrelated sqlite failures
 * exist elsewhere). Builds only the minimal schema required for the lifecycle
 * domain invariants.
 */

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Profile;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase1Schema();
});

afterEach(function () {
    dropPhase1Schema();
});

function buildPhase1Schema(): void
{
    dropPhase1Schema();

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
        $table->string('slug')->nullable();
        $table->string('email')->nullable();
        $table->string('phone_one')->nullable();
        $table->string('phone_two')->nullable();
        $table->string('logo')->nullable();
        $table->string('type')->default('private');
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('user_id')->nullable();
        $table->string('title')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('middle_name')->nullable();
        $table->string('gender')->nullable();
        $table->date('dob')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->string('short_code');
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
        $table->string('alias')->nullable();
        $table->text('description')->nullable();
        $table->integer('sequence')->default(1);
        $table->integer('max_arms')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name', 25);
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_current')->default(false);
        $table->string('status', 20)->default('draft');
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('students', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id');
        $table->uuid('school_id');
        $table->string('admission_number')->nullable();
        $table->date('admission_date')->nullable();
        $table->string('admission_type')->nullable();
        $table->string('status', 50)->default('admitted');
        $table->uuid('application_id')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('student_applications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->uuid('school_section_id')->nullable();
        $table->uuid('class_level_id')->nullable();
        $table->string('first_name', 100);
        $table->string('last_name', 100);
        $table->string('middle_name', 100)->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('gender', 30)->nullable();
        $table->string('phone', 30)->nullable();
        $table->string('email', 191)->nullable();
        $table->string('source', 30)->default('admin_direct');
        $table->string('status', 30)->default('pending');
        $table->string('application_number', 50)->nullable();
        $table->string('application_token', 100)->nullable();
        $table->uuid('student_id')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('student_id')->nullable();
        $table->uuid('application_id')->nullable();
        $table->uuid('class_level_id');
        $table->uuid('school_section_id')->nullable();
        $table->uuid('academic_session_id');
        $table->string('roll_no')->nullable();
        $table->string('status')->default('offered');
        $table->timestamp('offered_at')->nullable();
        $table->timestamp('acceptance_deadline')->nullable();
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('declined_at')->nullable();
        $table->timestamp('expired_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->text('notes')->nullable();
        $table->json('configs')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('student_id');
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
}

function dropPhase1Schema(): void
{
    Schema::dropIfExists('enrollments');
    Schema::dropIfExists('admissions');
    Schema::dropIfExists('student_applications');
    Schema::dropIfExists('students');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('class_levels');
    Schema::dropIfExists('school_sections');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
}

function uuid(): string
{
    return (string) Str::uuid();
}

function seedSchoolGraph(): array
{
    $schoolId = uuid();
    School::query()->create([
        'id' => $schoolId,
        'name' => 'Test School '.Str::random(4),
        'code' => Str::upper(Str::random(6)),
        'slug' => 'school-'.Str::lower(Str::random(6)),
        'email' => Str::random(8).'@example.test',
    ]);

    $sessionId = uuid();
    AcademicSession::query()->create([
        'id' => $sessionId,
        'school_id' => $schoolId,
        'name' => '2025/2026',
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-31',
        'is_current' => true,
        'status' => 'active',
    ]);

    $sectionId = uuid();
    SchoolSection::query()->create([
        'id' => $sectionId,
        'school_id' => $schoolId,
        'name' => 'Primary',
        'display_name' => 'Primary',
        'short_code' => 'PRI',
        'source' => 'custom',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $classLevelId = uuid();
    ClassLevel::query()->create([
        'id' => $classLevelId,
        'school_section_id' => $sectionId,
        'name' => 'Primary 1',
        'sequence' => 1,
        'is_active' => true,
    ]);

    return [
        'school_id' => $schoolId,
        'session_id' => $sessionId,
        'section_id' => $sectionId,
        'class_level_id' => $classLevelId,
    ];
}

function seedStudent(string $schoolId): string
{
    $profileId = uuid();
    Profile::query()->create([
        'id' => $profileId,
        'first_name' => 'Test',
        'last_name' => 'Student',
    ]);

    $studentId = uuid();
    Student::query()->create([
        'id' => $studentId,
        'profile_id' => $profileId,
        'school_id' => $schoolId,
        'admission_number' => 'ADM-'.Str::upper(Str::random(6)),
        'status' => 'admitted',
    ]);

    return $studentId;
}

test('application belongs to the correct school', function () {
    $g = seedSchoolGraph();

    $app = StudentApplication::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'academic_session_id' => $g['session_id'],
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'status' => 'pending',
        'source' => 'admin_direct',
    ]);

    expect($app->school_id)->toBe($g['school_id'])
        ->and($app->school->id)->toBe($g['school_id']);
});

test('multiple applications for the same candidate are permitted', function () {
    $g = seedSchoolGraph();

    $attrs = [
        'school_id' => $g['school_id'],
        'academic_session_id' => $g['session_id'],
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-01-01',
        'status' => 'pending',
        'source' => 'admin_direct',
    ];

    StudentApplication::query()->create(array_merge($attrs, ['id' => uuid()]));
    StudentApplication::query()->create(array_merge($attrs, ['id' => uuid()]));

    expect(
        StudentApplication::query()
            ->where('school_id', $g['school_id'])
            ->where('first_name', 'Ada')
            ->count()
    )->toBe(2);
});

test('application can exist without a profile user or student', function () {
    $g = seedSchoolGraph();

    $app = StudentApplication::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'first_name' => 'No',
        'last_name' => 'Student',
        'student_id' => null,
        'status' => 'pending',
        'source' => 'public_portal',
    ]);

    expect($app->student_id)->toBeNull()
        ->and($app->student)->toBeNull();
});

test('application exposes admissions relationship', function () {
    $g = seedSchoolGraph();

    $app = StudentApplication::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'first_name' => 'Rel',
        'last_name' => 'Test',
        'status' => 'pending',
        'source' => 'admin_direct',
    ]);

    $admission = Admission::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'application_id' => $app->id,
        'class_level_id' => $g['class_level_id'],
        'academic_session_id' => $g['session_id'],
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
    ]);

    expect($app->admissions)->toHaveCount(1)
        ->and($app->admissions->first()->is($admission))->toBeTrue()
        ->and($admission->application->is($app))->toBeTrue();
});

test('admission can exist without an application or student', function () {
    $g = seedSchoolGraph();

    $admission = Admission::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'application_id' => null,
        'student_id' => null,
        'class_level_id' => $g['class_level_id'],
        'academic_session_id' => $g['session_id'],
        'status' => Admission::STATUS_OFFERED,
    ]);

    expect($admission->application_id)->toBeNull()
        ->and($admission->student_id)->toBeNull()
        ->and($admission->application)->toBeNull()
        ->and($admission->student)->toBeNull();
});

test('admission belongs to school session and class level', function () {
    $g = seedSchoolGraph();

    $admission = Admission::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'class_level_id' => $g['class_level_id'],
        'academic_session_id' => $g['session_id'],
        'status' => Admission::STATUS_OFFERED,
    ]);

    expect($admission->school->id)->toBe($g['school_id'])
        ->and($admission->academicSession->id)->toBe($g['session_id'])
        ->and($admission->classLevel->id)->toBe($g['class_level_id']);
});

test('an admission cannot have more than one enrollment', function () {
    $g = seedSchoolGraph();
    $studentId = seedStudent($g['school_id']);

    $admissionId = uuid();
    Admission::query()->create([
        'id' => $admissionId,
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'class_level_id' => $g['class_level_id'],
        'academic_session_id' => $g['session_id'],
        'status' => Admission::STATUS_ACCEPTED,
    ]);

    Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $g['session_id'],
        'admission_id' => $admissionId,
        'status' => Enrollment::STATUS_DRAFT,
    ]);

    expect(fn () => Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $g['session_id'],
        'admission_id' => $admissionId,
        'status' => Enrollment::STATUS_IN_PROGRESS,
    ]))->toThrow(QueryException::class);
});

test('enrollment belongs to school student and academic session', function () {
    $g = seedSchoolGraph();
    $studentId = seedStudent($g['school_id']);

    $enrollment = Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $g['session_id'],
        'status' => Enrollment::STATUS_DRAFT,
    ]);

    expect($enrollment->school->id)->toBe($g['school_id'])
        ->and($enrollment->student->id)->toBe($studentId)
        ->and($enrollment->academicSession->id)->toBe($g['session_id']);
});

test('enrollment may exist without an admission', function () {
    $g = seedSchoolGraph();
    $studentId = seedStudent($g['school_id']);

    $enrollment = Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $g['session_id'],
        'admission_id' => null,
        'status' => Enrollment::STATUS_IN_PROGRESS,
    ]);

    expect($enrollment->admission_id)->toBeNull()
        ->and($enrollment->admission)->toBeNull()
        ->and($enrollment->isIncomplete())->toBeTrue();
});

test('enrollment status distinguishes incomplete from active', function () {
    $g = seedSchoolGraph();
    $studentId = seedStudent($g['school_id']);

    $draft = Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $g['session_id'],
        'status' => Enrollment::STATUS_DRAFT,
    ]);

    $session2 = uuid();
    AcademicSession::query()->create([
        'id' => $session2,
        'school_id' => $g['school_id'],
        'name' => '2026/2027',
        'start_date' => '2026-09-01',
        'end_date' => '2027-07-31',
        'is_current' => false,
        'status' => 'upcoming',
    ]);

    $active = Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $g['school_id'],
        'student_id' => $studentId,
        'academic_session_id' => $session2,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    expect($draft->isIncomplete())->toBeTrue()
        ->and($draft->isActive())->toBeFalse()
        ->and($active->isActive())->toBeTrue()
        ->and($active->isIncomplete())->toBeFalse();
});

test('student belongs to profile and is school-scoped', function () {
    $g = seedSchoolGraph();
    $studentId = seedStudent($g['school_id']);

    $student = Student::query()->findOrFail($studentId);

    expect($student->school_id)->toBe($g['school_id'])
        ->and($student->profile)->not->toBeNull();
});

test('same profile can be a student at multiple schools', function () {
    $gA = seedSchoolGraph();
    $gB = seedSchoolGraph();

    $profileId = uuid();
    Profile::query()->create([
        'id' => $profileId,
        'first_name' => 'Multi',
        'last_name' => 'School',
    ]);

    $studentA = uuid();
    Student::query()->create([
        'id' => $studentA,
        'profile_id' => $profileId,
        'school_id' => $gA['school_id'],
        'status' => 'admitted',
    ]);

    $studentB = uuid();
    Student::query()->create([
        'id' => $studentB,
        'profile_id' => $profileId,
        'school_id' => $gB['school_id'],
        'status' => 'admitted',
    ]);

    expect($studentA)->not->toBe($studentB)
        ->and(Student::query()->where('profile_id', $profileId)->count())->toBe(2);
});

test('admission cannot link to an application from another school', function () {
    $gA = seedSchoolGraph();
    $gB = seedSchoolGraph();

    $appB = StudentApplication::query()->create([
        'id' => uuid(),
        'school_id' => $gB['school_id'],
        'first_name' => 'Other',
        'last_name' => 'School',
        'status' => 'pending',
        'source' => 'admin_direct',
    ]);

    expect(fn () => Admission::query()->create([
        'id' => uuid(),
        'school_id' => $gA['school_id'],
        'application_id' => $appB->id,
        'class_level_id' => $gA['class_level_id'],
        'academic_session_id' => $gA['session_id'],
        'status' => Admission::STATUS_OFFERED,
    ]))->toThrow(InvalidArgumentException::class);
});

test('enrollment cannot link to a student from another school', function () {
    $gA = seedSchoolGraph();
    $gB = seedSchoolGraph();
    $studentB = seedStudent($gB['school_id']);

    expect(fn () => Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $gA['school_id'],
        'student_id' => $studentB,
        'academic_session_id' => $gA['session_id'],
        'status' => Enrollment::STATUS_DRAFT,
    ]))->toThrow(InvalidArgumentException::class);
});

test('enrollment cannot link to a session from another school', function () {
    $gA = seedSchoolGraph();
    $gB = seedSchoolGraph();
    $studentA = seedStudent($gA['school_id']);

    expect(fn () => Enrollment::query()->create([
        'id' => uuid(),
        'school_id' => $gA['school_id'],
        'student_id' => $studentA,
        'academic_session_id' => $gB['session_id'],
        'status' => Enrollment::STATUS_DRAFT,
    ]))->toThrow(InvalidArgumentException::class);
});
