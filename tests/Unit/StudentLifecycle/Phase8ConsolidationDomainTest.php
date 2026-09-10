<?php

uses(Tests\TestCase::class);

/**
 * Phase 8 — Consolidation, school isolation, integrity, and shim safety.
 *
 * Schema mirrors production: AcademicSession SoftDeletes, placed_by,
 * requirement definition SoftDeletes+sort_order, registration_number_* + id_sequences.
 */

use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\Services\Student\EnrollmentService;
use App\Services\Student\PlacementAllocationService;
use App\Services\Student\RegistrationNumberService;
use App\Services\Student\StudentPlacementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    p8BuildSchema();
});

afterEach(function () {
    p8DropSchema();
});

function p8DropSchema(): void
{
    foreach ([
        'registration_number_assignments',
        'registration_number_histories',
        'id_sequences',
        'student_session_placements',
        'enrollment_requirement_instances',
        'enrollment_requirement_definitions',
        'enrollments',
        'admissions',
        'students',
        'class_sections',
        'class_levels',
        'school_sections',
        'academic_sessions',
        'profiles',
        'users',
        'settings',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function p8BuildSchema(): void
{
    p8DropSchema();

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        $t->string('slug')->nullable();
        $t->boolean('is_active')->default(true);
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('key');
        $t->json('value')->nullable();
        $t->nullableUuidMorphs('model');
        $t->timestamps();
    });
    Schema::create('users', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->timestamps();
    });
    Schema::create('profiles', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('user_id')->nullable();
        $t->string('first_name')->nullable();
        $t->string('middle_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('gender')->nullable();
        $t->date('date_of_birth')->nullable();
        $t->string('phone')->nullable();
        $t->string('email')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->date('start_date')->nullable();
        $t->boolean('is_current')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('school_sections', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('class_levels', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_section_id');
        $t->string('name');
        $t->integer('sort_order')->default(0);
        $t->integer('sequence')->default(0);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('class_sections', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('class_level_id');
        $t->string('name');
        $t->string('display_name')->nullable();
        $t->integer('capacity')->default(0);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('admissions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('application_id')->nullable();
        $t->uuid('student_id')->nullable();
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->string('status')->default('offered');
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('student_id')->nullable();
        $t->uuid('academic_session_id');
        $t->uuid('admission_id')->nullable();
        $t->string('status')->default('in_progress');
        $t->timestamp('started_at')->nullable();
        $t->timestamp('activated_at')->nullable();
        $t->text('notes')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('profile_id');
        $t->uuid('school_id');
        $t->string('admission_number')->nullable();
        $t->string('status')->default('active');
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['school_id', 'profile_id']);
    });
    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->id();
        $t->uuid('student_id');
        $t->uuid('school_id')->nullable();
        $t->uuid('enrollment_id')->nullable();
        $t->uuid('academic_session_id');
        $t->uuid('class_level_id');
        $t->uuid('class_section_id')->nullable();
        $t->string('registration_number', 64)->nullable();
        $t->timestamp('joined_at')->nullable();
        $t->date('enrolled_at')->nullable();
        $t->date('left_at')->nullable();
        $t->boolean('is_current')->default(false);
        $t->string('promotion_outcome', 50)->nullable();
        $t->text('notes')->nullable();
        $t->boolean('capacity_override_used')->default(false);
        $t->uuid('placed_by')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
    });
    Schema::create('registration_number_histories', function (Blueprint $t) {
        $t->id();
        $t->uuid('student_id');
        $t->uuid('school_id');
        $t->uuid('enrollment_id')->nullable();
        $t->unsignedBigInteger('placement_id')->nullable();
        $t->string('registration_number', 64);
        $t->string('scope_key', 191)->nullable();
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->uuid('class_section_id')->nullable();
        $t->string('reason', 64)->nullable();
        $t->timestamp('effective_from')->nullable();
        $t->timestamp('effective_to')->nullable();
        $t->uuid('assigned_by')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
    });
    Schema::create('registration_number_assignments', function (Blueprint $t) {
        $t->id();
        $t->uuid('school_id');
        $t->string('scope_key', 191);
        $t->string('registration_number', 64);
        $t->uuid('student_id');
        $t->unsignedBigInteger('history_id')->nullable();
        $t->timestamps();
        $t->unique(['school_id', 'scope_key', 'registration_number'], 'uq_p8_regnum_assignment_active');
        $t->unique(['school_id', 'student_id'], 'uq_p8_regnum_assignment_student');
    });
    Schema::create('id_sequences', function (Blueprint $t) {
        $t->id();
        $t->string('type', 64);
        $t->uuid('school_id')->nullable();
        $t->string('scope_key', 191)->default('');
        $t->unsignedInteger('year')->default(0);
        $t->unsignedBigInteger('last_value')->default(0);
        $t->timestamps();
        $t->unique(['type', 'school_id', 'scope_key', 'year'], 'uq_p8_id_sequences_scope');
    });
    Schema::create('enrollment_requirement_definitions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('code');
        $t->string('name');
        $t->string('type')->default('document');
        $t->boolean('is_required')->default(true);
        $t->boolean('is_active')->default(true);
        $t->integer('sort_order')->default(0);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('enrollment_requirement_instances', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('enrollment_id');
        $t->uuid('definition_id');
        $t->string('status')->default('pending');
        $t->timestamps();
    });
}

function p8School(string $name = 'School A'): School
{
    return School::query()->create([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 3)),
        'slug' => Str::slug($name).'-'.Str::random(4),
        'is_active' => true,
    ]);
}

function p8User(): User
{
    return User::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Actor',
        'email' => 'actor-'.Str::random(6).'@example.test',
    ]);
}

function p8Session(School $school): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $school->id,
        'name' => '2026/2027',
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id, 'school_id' => $school->id];
}

function p8Student(School $school, ?Profile $profile = null): Student
{
    $profile = $profile ?? Profile::query()->create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-'.Str::random(5).'@example.test',
    ]);

    return Student::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'profile_id' => $profile->id,
        'status' => 'active',
    ]);
}

function p8LevelAndSection(School $school, int $capacity = 1): array
{
    $ssId = (string) Str::uuid();
    DB::table('school_sections')->insert([
        'id' => $ssId,
        'school_id' => $school->id,
        'name' => 'Primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $levelId = (string) Str::uuid();
    DB::table('class_levels')->insert([
        'id' => $levelId,
        'school_section_id' => $ssId,
        'name' => 'JSS 1',
        'sort_order' => 1,
        'sequence' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $sectionId = (string) Str::uuid();
    DB::table('class_sections')->insert([
        'id' => $sectionId,
        'school_id' => $school->id,
        'class_level_id' => $levelId,
        'name' => 'A',
        'display_name' => 'JSS 1A',
        'capacity' => $capacity,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$levelId, $sectionId];
}

function p8Pas(): PlacementAllocationService
{
    return new PlacementAllocationService(
        new RegistrationNumberService(),
        new StudentPlacementService()
    );
}

test('obsolete StudentEnrollmentService classes are removed', function () {
    expect(class_exists(\App\Services\Student\StudentEnrollmentService::class))->toBeFalse();
    expect(class_exists(\App\Services\UserManagement\StudentEnrollmentService::class))->toBeFalse();
});

test('StudentController does not import removed StudentEnrollmentService', function () {
    $src = file_get_contents(app_path('Http/Controllers/Student/StudentController.php'));
    expect($src)->not->toContain('use App\\Services\\Student\\StudentEnrollmentService;');
    expect($src)->toContain('use App\\Services\\Student\\EnrollmentService;');
    expect($src)->not->toContain('enrollmentService->(');
});

test('StudentPlacementController destroy delegates to PlacementAllocationService', function () {
    $src = file_get_contents(app_path('Http/Controllers/Student/StudentPlacementController.php'));
    expect($src)->toContain('closeCurrentPlacement');
    expect($src)->not->toContain("StudentSessionPlacement::query()\n                ->where('student_id'");
});

test('Academic Student shim shares table, traits and relationship methods', function () {
    $canonical = new Student();
    $shim = new \App\Models\Academic\Student();

    expect(is_subclass_of(\App\Models\Academic\Student::class, Student::class))->toBeTrue();
    expect($shim->getTable())->toBe($canonical->getTable());
    expect($shim->getKeyName())->toBe($canonical->getKeyName());

    $traits = class_uses_recursive(\App\Models\Academic\Student::class);
    expect($traits)->toHaveKey(\App\Traits\BelongsToSchool::class);

    foreach (['profile', 'enrollments', 'sessionPlacements'] as $rel) {
        expect(method_exists($shim, $rel))->toBeTrue();
        expect(method_exists($canonical, $rel))->toBeTrue();
    }

    $school = p8School('Shim School');
    $student = p8Student($school);
    expect($student)->toBeInstanceOf(Student::class);
    expect($student instanceof \App\Models\Academic\Student)->toBeFalse();

    $viaShim = \App\Models\Academic\Student::query()->whereKey($student->id)->first();
    expect($viaShim)->not->toBeNull();
    expect($viaShim->id)->toBe($student->id);
    expect($viaShim)->toBeInstanceOf(Student::class);
});

test('EnrollmentService rejects admission belonging to another school', function () {
    $schoolA = p8School('Alpha');
    $schoolB = p8School('Beta');
    $user = p8User();
    $sessionA = p8Session($schoolA);
    $sessionB = p8Session($schoolB);

    $admissionB = Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => 'accepted',
    ]);

    $service = app(EnrollmentService::class);

    expect(fn () => $service->start($schoolA, $user, [
        'academic_session_id' => $sessionA->id,
        'admission_id' => $admissionB->id,
        'source' => 'admission',
    ]))->toThrow(ValidationException::class);
});

test('EnrollmentService rejects academic session belonging to another school', function () {
    $schoolA = p8School('Delta');
    $schoolB = p8School('Epsilon');
    $user = p8User();
    $sessionB = p8Session($schoolB);

    $service = app(EnrollmentService::class);

    expect(fn () => $service->start($schoolA, $user, [
        'academic_session_id' => $sessionB->id,
        'source' => 'direct',
        'biodata' => ['first_name' => 'X', 'last_name' => 'Y'],
    ]))->toThrow(ValidationException::class);
});

test('PlacementAllocationService rejects placing a student from another school', function () {
    $schoolA = p8School('Place A');
    $schoolB = p8School('Place B');
    $user = p8User();
    $sessionA = p8Session($schoolA);
    [$levelA, $sectionA] = p8LevelAndSection($schoolA, 10);
    $studentB = p8Student($schoolB);

    $pas = p8Pas();

    expect(fn () => $pas->placeManually(
        $studentB,
        $schoolA,
        $levelA,
        $sectionA,
        $user,
        ['academic_session_id' => $sessionA->id]
    ))->toThrow(ValidationException::class);
});

test('closeCurrentPlacement rejects cross-school student', function () {
    $schoolA = p8School('Close A');
    $schoolB = p8School('Close B');
    $user = p8User();
    $studentB = p8Student($schoolB);

    $pas = p8Pas();

    expect(fn () => $pas->closeCurrentPlacement($studentB, $schoolA, $user))
        ->toThrow(ValidationException::class);
});

test('closeCurrentPlacement closes only the school current placements', function () {
    $school = p8School('Close Own');
    $user = p8User();
    $session = p8Session($school);
    [$levelId, $sectionId] = p8LevelAndSection($school, 5);
    $student = p8Student($school);

    StudentSessionPlacement::query()->create([
        'student_id' => $student->id,
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'class_level_id' => $levelId,
        'class_section_id' => $sectionId,
        'is_current' => true,
        'joined_at' => now(),
        'enrolled_at' => now()->toDateString(),
        'left_at' => null,
    ]);

    $pas = p8Pas();
    $closed = $pas->closeCurrentPlacement($student, $school, $user, [
        'academic_session_id' => $session->id,
        'notes' => 'Phase 8 test close',
    ]);

    expect($closed)->toBe(1);
    $row = StudentSessionPlacement::query()->where('student_id', $student->id)->first();
    expect($row->is_current)->toBeFalse();
    expect($row->left_at)->not->toBeNull();
});

test('double finalize is rejected after first successful finalization attempt path', function () {
    $school = p8School('Gamma');
    $user = p8User();
    $session = p8Session($school);

    DB::table('settings')->insert([
        'key' => 'academic.enrollment',
        'value' => json_encode(['allow_unpaid' => true]),
        'model_type' => School::class,
        'model_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(EnrollmentService::class);

    $enrollment = $service->start($school, $user, [
        'academic_session_id' => $session->id,
        'source' => 'direct',
        'biodata' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada-phase8-'.Str::random(4).'@example.test',
        ],
    ]);

    $service->updateBiodata($enrollment, $user, [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-phase8-'.Str::random(4).'@example.test',
    ]);

    $profile = Profile::query()->create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-fin-'.Str::random(4).'@example.test',
    ]);
    $student = p8Student($school, $profile);
    $enrollment->student_id = $student->id;
    $enrollment->status = Enrollment::STATUS_ACTIVE;
    $enrollment->activated_at = now();
    $enrollment->save();

    expect(fn () => $service->finalize($enrollment->fresh(), $user))
        ->toThrow(ValidationException::class);
});

test('placeManually respects section capacity without override', function () {
    $school = p8School('Cap School');
    $user = p8User();
    $session = p8Session($school);
    [$levelId, $sectionId] = p8LevelAndSection($school, 1);
    $student1 = p8Student($school);
    $student2 = p8Student($school);

    $pas = p8Pas();

    $pas->placeManually($student1, $school, $levelId, $sectionId, $user, [
        'academic_session_id' => $session->id,
    ]);

    expect(fn () => $pas->placeManually($student2, $school, $levelId, $sectionId, $user, [
        'academic_session_id' => $session->id,
        'capacity_override' => false,
    ]))->toThrow(ValidationException::class);

    $count = StudentSessionPlacement::query()
        ->where('class_section_id', $sectionId)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count();
    expect($count)->toBe(1);
});
