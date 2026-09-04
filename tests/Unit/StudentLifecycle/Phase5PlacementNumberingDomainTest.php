<?php

uses(Tests\TestCase::class);

/**
 * Phase 5 — Placement & Registration Numbers domain tests.
 *
 * Concurrency note: true multi-writer races require pgsql/mysql + pcntl_fork.
 * Unsupported environments call markTestSkipped (not a false green pass).
 */

use App\Helpers\IdGenerator;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\IdSequence;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\RegistrationNumberHistory;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\Services\Student\PlacementAllocationService;
use App\Services\Student\RegistrationNumberService;
use App\Services\Student\StudentPlacementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase5Schema();
});

afterEach(function () {
    dropPhase5Schema();
});

function dropPhase5Schema(): void
{
    Schema::dropIfExists('registration_number_assignments');
    Schema::dropIfExists('registration_number_histories');
    Schema::dropIfExists('student_session_placements');
    Schema::dropIfExists('student_class_section_pivot');
    Schema::dropIfExists('class_sections');
    Schema::dropIfExists('class_levels');
    Schema::dropIfExists('school_sections');
    Schema::dropIfExists('enrollments');
    Schema::dropIfExists('students');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::dropIfExists('id_sequences');
    Schema::dropIfExists('permission_role');
    Schema::dropIfExists('permission_user');
    Schema::dropIfExists('role_user');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('settings');
    Schema::dropIfExists('schools');
}

function buildPhase5Schema(): void
{
    dropPhase5Schema();
    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        // School model auto-generates slug and merges extras into data on create.
        $t->string('slug')->nullable();
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    // ruangdeveloper/laravel-settings — used by getMergedSettings / IdGenerator / RegistrationNumberService
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('key');
        $t->json('value')->nullable();
        $t->nullableUuidMorphs('model'); // model_type, model_id
        $t->timestamps();
    });
    // Minimal Laratrust tables so isAbleTo() can run against an unauthorized user
    // without "no such table: roles". No roles/permissions are seeded — user remains unauthorized.
    Schema::create('roles', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->unique();
        $t->string('display_name')->nullable();
        $t->string('description')->nullable();
        $t->uuid('school_id')->nullable();
        $t->timestamps();
    });
    Schema::create('permissions', function (Blueprint $t) {
        $t->id();
        $t->string('name')->unique();
        $t->string('display_name')->nullable();
        $t->string('description')->nullable();
        $t->timestamps();
    });
    Schema::create('role_user', function (Blueprint $t) {
        $t->uuid('role_id');
        $t->uuid('user_id');
        $t->string('user_type');
        $t->string('school_section_id')->nullable();
    });
    Schema::create('permission_user', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->uuid('user_id');
        $t->string('user_type');
        $t->string('school_section_id')->nullable();
    });
    Schema::create('permission_role', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->uuid('role_id');
    });
    Schema::create('users', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->timestamps();
    });
    Schema::create('profiles', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
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
        $t->integer('sort_order')->default(0);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('profile_id');
        $t->string('admission_number')->nullable();
        $t->date('admission_date')->nullable();
        $t->string('status', 50)->default('active');
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['school_id', 'profile_id'], 'uq_students_school_profile');
        $t->unique(['school_id', 'admission_number'], 'uq_students_school_admission_number');
    });
    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('student_id')->nullable();
        $t->uuid('school_id');
        $t->uuid('academic_session_id');
        $t->uuid('admission_id')->nullable();
        $t->string('status', 40)->default('draft');
        $t->timestamp('activated_at')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
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
        $t->unsignedBigInteger('promotion_batch_id')->nullable();
        $t->text('notes')->nullable();
        $t->boolean('capacity_override_used')->default(false);
        $t->uuid('placed_by')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->index(['class_section_id', 'is_current', 'left_at']);
        $t->index(['student_id', 'academic_session_id', 'is_current']);
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
        $t->timestamp('effective_from');
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
        $t->unique(['school_id', 'scope_key', 'registration_number'], 'uq_regnum_assignment_active');
        $t->unique(['school_id', 'student_id'], 'uq_regnum_assignment_student');
    });
    Schema::create('id_sequences', function (Blueprint $t) {
        $t->id();
        $t->string('type', 64);
        $t->uuid('school_id')->nullable();
        $t->string('scope_key', 191)->default('');
        $t->unsignedInteger('year')->default(0);
        $t->unsignedBigInteger('last_value')->default(0);
        $t->timestamps();
        $t->unique(['type', 'school_id', 'scope_key', 'year'], 'uq_id_sequences_scope');
    });
    Schema::create('student_class_section_pivot', function (Blueprint $t) {
        $t->id();
        $t->uuid('student_id');
        $t->uuid('class_section_id');
        $t->uuid('academic_session_id')->nullable();
        $t->boolean('is_current')->default(false);
        $t->date('enrolled_at')->nullable();
        $t->date('left_at')->nullable();
        $t->timestamps();
    });
}

function p5School(string $name = 'Alpha', string $code = 'ALP'): School
{
    return School::query()->create(['id' => (string) Str::uuid(), 'name' => $name, 'code' => $code]);
}
function p5User(): User
{
    return User::query()->create(['id' => (string) Str::uuid(), 'name' => 'Staff', 'email' => Str::random(8) . '@t.local']);
}
function p5Profile(): Profile
{
    return Profile::query()->create(['id' => (string) Str::uuid(), 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => Str::random(6) . '@ex.test']);
}
function p5Student(School $school, ?Profile $profile = null): Student
{
    $profile ??= p5Profile();
    return Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'profile_id' => $profile->id, 'status' => 'active']);
}
function p5Session(School $school, string $name = '2026/2027'): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert(['id' => $id, 'school_id' => $school->id, 'name' => $name, 'start_date' => '2026-09-01', 'is_current' => true, 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id, 'name' => $name];
}
function p5SchoolSection(School $school): object
{
    $id = (string) Str::uuid();
    DB::table('school_sections')->insert(['id' => $id, 'school_id' => $school->id, 'name' => 'Junior', 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id];
}
function p5Level(School $school, ?object $schoolSection = null, string $name = 'JSS1'): ClassLevel
{
    $schoolSection ??= p5SchoolSection($school);
    $id = (string) Str::uuid();
    DB::table('class_levels')->insert(['id' => $id, 'school_section_id' => $schoolSection->id, 'name' => $name, 'sort_order' => 10, 'sequence' => 10, 'created_at' => now(), 'updated_at' => now()]);
    return ClassLevel::query()->withoutGlobalScopes()->findOrFail($id);
}
function p5Section(School $school, ClassLevel $level, string $name, int $capacity, int $sort): ClassSection
{
    return ClassSection::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'class_level_id' => $level->id, 'name' => $name, 'display_name' => $level->name . $name, 'capacity' => $capacity, 'sort_order' => $sort]);
}
function p5Enrollment(School $school, object $session, ?Student $student = null): Enrollment
{
    return Enrollment::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'academic_session_id' => $session->id, 'student_id' => $student?->id, 'status' => Enrollment::STATUS_ACTIVE, 'activated_at' => now(), 'meta' => []]);
}
function p5Services(): array
{
    $reg = new RegistrationNumberService();
    $place = new StudentPlacementService();
    $alloc = new PlacementAllocationService($reg, $place);
    return compact('reg', 'place', 'alloc');
}
function p5DriverSupportsConcurrentConnections(): bool
{
    return in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'mysql', 'mariadb'], true);
}

it('generates sequential school-scoped admission numbers via id_sequences', function () {
    $school = p5School();
    $a = IdGenerator::generate('admission_number', $school);
    $b = IdGenerator::generate('admission_number', $school);
    expect($a)->not->toBe($b)->and((int) IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->value('last_value'))->toBeGreaterThanOrEqual(2);
});

it('allows the same formatted admission number in different schools', function () {
    $a = p5School('A', 'AAA');
    $b = p5School('B', 'BBB');
    IdGenerator::generate('admission_number', $a);
    IdGenerator::generate('admission_number', $b);
    expect(IdSequence::query()->where('school_id', $a->id)->where('type', 'admission_number')->exists())->toBeTrue();
});

it('rejects mutating an assigned admission number', function () {
    $school = p5School();
    $student = p5Student($school);
    $student->admission_number = 'ADM/2026/00001';
    $student->save();
    $student->admission_number = 'HACKED';
    $student->save();
})->throws(ValidationException::class);

it('enforces database uniqueness of admission numbers within a school', function () {
    $school = p5School();
    $s1 = p5Student($school);
    $s2 = p5Student($school);
    $s1->admission_number = 'ADM/2026/00001';
    $s1->save();
    $s2->admission_number = 'ADM/2026/00001';
    $s2->save();
})->throws(\Illuminate\Database\QueryException::class);

it('does not regenerate admission number on subsequent ensure calls', function () {
    $school = p5School();
    $student = p5Student($school);
    ['alloc' => $alloc] = p5Services();
    $first = $alloc->ensureAdmissionNumber($student, $school);
    $second = $alloc->ensureAdmissionNumber($student->fresh(), $school);
    expect($second)->toBe($first);
});

it('requires id_sequences for admission_number and does not fall back to cache', function () {
    Schema::dropIfExists('id_sequences');
    $school = p5School();
    IdGenerator::generate('admission_number', $school);
})->throws(\RuntimeException::class);

it('auto-allocates first available section by sort_order', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $full = p5Section($school, $level, 'A', 1, 10);
    $next = p5Section($school, $level, 'B', 30, 20);
    ['alloc' => $alloc] = p5Services();
    $filler = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $filler), $filler, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $full->id]);
    $student = p5Student($school);
    $placement = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id]);
    expect($placement->class_section_id)->toBe($next->id)->and($placement->is_current)->toBeTrue();
});

it('rejects automatic allocation when all sections are full', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();
    $filler = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $filler), $filler, $school, $user, ['class_level_id' => $level->id]);
    $student = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id]);
})->throws(ValidationException::class);

it('treats capacity 0 as unlimited', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 0, 10);
    ['alloc' => $alloc] = p5Services();
    for ($i = 0; $i < 5; $i++) {
        $student = p5Student($school);
        $placement = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
        expect($placement->class_section_id)->toBe($section->id);
    }
});

it('rejects full section without override', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();
    $filler = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $filler), $filler, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    $student = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id, 'capacity_override' => false]);
})->throws(ValidationException::class);

it('rejects capacity override without authorization', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();
    $filler = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $filler), $filler, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    $student = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id, 'capacity_override' => true]);
})->throws(ValidationException::class);

it('does not overfill capacity-1 section under sequential pressure', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();
    $s1 = p5Student($school);
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $s1), $s1, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    $s2 = p5Student($school);
    expect(fn() => $alloc->allocateForEnrollment(p5Enrollment($school, $session, $s2), $s2, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]))->toThrow(ValidationException::class);
    expect(StudentSessionPlacement::query()
        ->where('class_section_id', $section->id)
        ->where('academic_session_id', $session->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(1);
});

it('does not count prior-session placements against new-session capacity', function () {
    $school = p5School();
    $user = p5User();
    $sessionOld = p5Session($school, '2025/2026');
    $sessionNew = p5Session($school, '2026/2027');
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();

    $prior = p5Student($school);
    $alloc->allocateForEnrollment(
        p5Enrollment($school, $sessionOld, $prior),
        $prior,
        $school,
        $user,
        ['class_level_id' => $level->id, 'class_section_id' => $section->id]
    );
    expect($prior->fresh()->currentPlacement?->is_current)->not->toBeNull();
    expect(StudentSessionPlacement::query()
        ->where('class_section_id', $section->id)
        ->where('academic_session_id', $sessionOld->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(1);

    // Prior-session placement remains current (history is session-scoped), but must
    // not block capacity-1 allocation in the new session.
    $next = p5Student($school);
    $placement = $alloc->allocateForEnrollment(
        p5Enrollment($school, $sessionNew, $next),
        $next,
        $school,
        $user,
        ['class_level_id' => $level->id, 'class_section_id' => $section->id]
    );
    expect($placement->class_section_id)->toBe($section->id)
        ->and($placement->academic_session_id)->toBe($sessionNew->id)
        ->and($placement->is_current)->toBeTrue();
    expect(StudentSessionPlacement::query()
        ->where('class_section_id', $section->id)
        ->where('academic_session_id', $sessionNew->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(1);
    // Prior session placement is still current for its own session.
    expect(StudentSessionPlacement::query()
        ->where('class_section_id', $section->id)
        ->where('academic_session_id', $sessionOld->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(1);
});

it('rejects section from wrong class level', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $ss = p5SchoolSection($school);
    $levelA = p5Level($school, $ss, 'JSS1');
    $levelB = p5Level($school, $ss, 'JSS2');
    $sectionB = p5Section($school, $levelB, 'A', 30, 10);
    $student = p5Student($school);
    ['alloc' => $alloc] = p5Services();
    $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $levelA->id, 'class_section_id' => $sectionB->id]);
})->throws(ValidationException::class);

it('rejects cross-school section placement', function () {
    $schoolA = p5School('A', 'AAA');
    $schoolB = p5School('B', 'BBB');
    $user = p5User();
    $session = p5Session($schoolA);
    $levelA = p5Level($schoolA);
    $sectionB = p5Section($schoolB, p5Level($schoolB), 'A', 30, 10);
    $student = p5Student($schoolA);
    ['alloc' => $alloc] = p5Services();
    $alloc->allocateForEnrollment(p5Enrollment($schoolA, $session, $student), $student, $schoolA, $user, ['class_level_id' => $levelA->id, 'class_section_id' => $sectionB->id]);
})->throws(ValidationException::class);

it('preserves placement history on manual move within the same session', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $secA = p5Section($school, $level, 'A', 30, 10);
    $secB = p5Section($school, $level, 'B', 30, 20);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();
    $first = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secA->id]);
    $second = $alloc->placeManually($student, $school, $level->id, $secB->id, $user, ['academic_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);
    $history = StudentSessionPlacement::query()->where('student_id', $student->id)->where('academic_session_id', $session->id)->orderBy('id')->get();
    expect($history)->toHaveCount(2)->and($history[0]->is_current)->toBeFalse()->and($history[1]->is_current)->toBeTrue();
});

it('does not close current placement from a different academic session', function () {
    $school = p5School();
    $user = p5User();
    $session1 = p5Session($school, '2025/2026');
    $session2 = p5Session($school, '2026/2027');
    $level = p5Level($school);
    $secA = p5Section($school, $level, 'A', 30, 10);
    $secB = p5Section($school, $level, 'B', 30, 20);
    $student = p5Student($school);
    ['alloc' => $alloc] = p5Services();
    $p1 = $alloc->allocateForEnrollment(p5Enrollment($school, $session1, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secA->id]);
    $p2 = $alloc->allocateForEnrollment(p5Enrollment($school, $session2, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secB->id]);
    expect($p1->fresh()->is_current)->toBeTrue()->and($p2->is_current)->toBeTrue();
});

it('maintains one current placement per student per session after moves', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $secA = p5Section($school, $level, 'A', 30, 10);
    $secB = p5Section($school, $level, 'B', 30, 20);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();
    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secA->id]);
    $alloc->placeManually($student, $school, $level->id, $secB->id, $user, ['academic_session_id' => $session->id]);
    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->where('academic_session_id', $session->id)->where('is_current', true)->count())->toBe(1);
});

it('assigns registration numbers and preserves history on regenerate', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    ['alloc' => $alloc, 'reg' => $reg] = p5Services();
    $placement = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    $first = $placement->registration_number;
    $second = $reg->regenerate($student, $school, $placement->fresh(), $user);
    expect($second)->not->toBe($first)->and($reg->history($student, $school->id))->toHaveCount(2);
});

it('allows same registration number in independent section scopes', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $secA = p5Section($school, $level, 'A', 30, 10);
    $secB = p5Section($school, $level, 'B', 30, 20);
    ['alloc' => $alloc] = p5Services();
    $s1 = p5Student($school);
    $p1 = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $s1), $s1, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secA->id]);
    $s2 = p5Student($school);
    $p2 = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $s2), $s2, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $secB->id]);
    expect($p1->registration_number)->toBe($p2->registration_number);
});

it('does not change admission number when registration number regenerates', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    ['alloc' => $alloc, 'reg' => $reg] = p5Services();
    $adm = $alloc->ensureAdmissionNumber($student, $school);
    $placement = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    $reg->regenerate($student, $school, $placement->fresh(), $user);
    expect($student->fresh()->admission_number)->toBe($adm);
});

it('enforces active registration number uniqueness via assignments table', function () {
    $school = p5School();
    DB::table('registration_number_assignments')->insert(['school_id' => $school->id, 'scope_key' => 'test-scope', 'registration_number' => '01', 'student_id' => p5Student($school)->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('registration_number_assignments')->insert(['school_id' => $school->id, 'scope_key' => 'test-scope', 'registration_number' => '01', 'student_id' => p5Student($school)->id, 'created_at' => now(), 'updated_at' => now()]);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces one current registration assignment per student per school at the database', function () {
    $school = p5School();
    $student = p5Student($school);
    DB::table('registration_number_assignments')->insert(['school_id' => $school->id, 'scope_key' => 'scope-a', 'registration_number' => '01', 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('registration_number_assignments')->insert(['school_id' => $school->id, 'scope_key' => 'scope-b', 'registration_number' => '02', 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
})->throws(\Illuminate\Database\QueryException::class);

it('uses integer placement ids referenced by registration history', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    ['alloc' => $alloc] = p5Services();
    $placement = $alloc->allocateForEnrollment(p5Enrollment($school, $session, $student), $student, $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
    expect(is_numeric($placement->id))->toBeTrue();
    $hist = RegistrationNumberHistory::query()->where('student_id', $student->id)->first();
    expect($hist)->not->toBeNull()->and($hist->placement_id)->toBe($placement->id);
});

it('does not produce duplicate sequence values under sequential allocation', function () {
    $school = p5School();
    $numbers = [];
    for ($i = 0; $i < 20; $i++) {
        $numbers[] = IdGenerator::generate('admission_number', $school);
    }
    expect(count($numbers))->toBe(count(array_unique($numbers)));
});

it('creates first sequence row safely without aborted-transaction trap', function () {
    $school = p5School();
    $a = IdGenerator::generate('registration_number', $school, 2026, 'scope-x');
    $b = IdGenerator::generate('registration_number', $school, 2026, 'scope-x');
    expect($a)->not->toBe($b)->and((int) IdSequence::query()->where('type', 'registration_number')->where('school_id', $school->id)->where('scope_key', 'scope-x')->value('last_value'))->toBe(2);
});

it('retries registration claim on unique collision without orphaned history or poisoned outer transaction', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    ['reg' => $reg] = p5Services();
    $scopeKey = $reg->buildScopeKey($school, $session->id, $level->id, $section->id);
    IdSequence::query()->create(['type' => 'registration_number', 'school_id' => $school->id, 'scope_key' => $scopeKey, 'year' => 2026, 'last_value' => 0]);
    $colliding = '01';
    DB::table('registration_number_assignments')->insert(['school_id' => $school->id, 'scope_key' => $scopeKey, 'registration_number' => $colliding, 'student_id' => p5Student($school)->id, 'created_at' => now(), 'updated_at' => now()]);
    $number = DB::transaction(function () use ($reg, $student, $school, $session, $level, $section, $user) {
        return $reg->assign($student, $school, ['academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $section->id], RegistrationNumberService::REASON_INITIAL, $user);
    });
    expect($number)->not->toBe($colliding)->and($reg->currentNumber($student, $school->id))->toBe($number);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->where('registration_number', $colliding)->whereNull('effective_to')->count())->toBe(0);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->where('registration_number', $number)->whereNull('effective_to')->count())->toBe(1);
    expect((int) IdSequence::query()->where('type', 'registration_number')->where('school_id', $school->id)->where('scope_key', $scopeKey)->value('last_value'))->toBe(2);
});

it('allows only one of two concurrent processes to claim the final capacity slot', function () {
    if (!p5DriverSupportsConcurrentConnections() || !function_exists('pcntl_fork')) {
        $this->markTestSkipped('Requires pgsql/mysql + pcntl_fork for multi-process concurrency');
    }
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    $s1 = p5Student($school);
    $s2 = p5Student($school);
    $e1 = p5Enrollment($school, $session, $s1);
    $e2 = p5Enrollment($school, $session, $s2);
    $tmp = tempnam(sys_get_temp_dir(), 'p5cap');
    file_put_contents($tmp, json_encode(['school_id' => $school->id, 'user_id' => $user->id, 'level_id' => $level->id, 'section_id' => $section->id, 'e1' => $e1->id, 'e2' => $e2->id, 's1' => $s1->id, 's2' => $s2->id]));
    $pids = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('fork failed');
        }
        if ($pid === 0) {
            DB::reconnect();
            $data = json_decode(file_get_contents($tmp), true);
            $school = School::query()->findOrFail($data['school_id']);
            $user = User::query()->findOrFail($data['user_id']);
            $student = Student::query()->findOrFail($i === 0 ? $data['s1'] : $data['s2']);
            $enrollment = Enrollment::query()->findOrFail($i === 0 ? $data['e1'] : $data['e2']);
            $alloc = new PlacementAllocationService(new RegistrationNumberService(), new StudentPlacementService());
            try {
                $alloc->allocateForEnrollment($enrollment, $student, $school, $user, ['class_level_id' => $data['level_id'], 'class_section_id' => $data['section_id']]);
                exit(0);
            } catch (Throwable $e) {
                exit(1);
            }
        }
        $pids[] = $pid;
    }
    $codes = [];
    foreach ($pids as $pid) use ($status) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wexitstatus($status);
    }
    expect(count(array_filter($codes, fn($c) => $c === 0)))->toBe(1)
        ->and(StudentSessionPlacement::query()->where('class_section_id', $section->id)->where('is_current', true)->whereNull('left_at')->count())->toBe(1);
})->group('concurrency');

it('prevents duplicate active registration assignments under sequential race-style claims', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    ['reg' => $reg] = p5Services();
    $s1 = p5Student($school);
    $s2 = p5Student($school);
    $scopeKey = $reg->buildScopeKey($school, $session->id, $level->id, $section->id);
    IdSequence::query()->create(['type' => 'registration_number', 'school_id' => $school->id, 'scope_key' => $scopeKey, 'year' => 2026, 'last_value' => 0]);
    $ctx = ['academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $section->id];
    $n1 = $reg->assign($s1, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    $n2 = $reg->assign($s2, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    expect($n1)->not->toBe($n2);
    $active = DB::table('registration_number_assignments')->where('school_id', $school->id)->where('scope_key', $scopeKey)->get();
    expect($active)->toHaveCount(2)->and($active->pluck('registration_number')->unique()->count())->toBe(2);
});

it('rolls back student admission number and placement when outer finalization transaction aborts', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();
    $thrown = false;
    try {
        DB::transaction(function () use ($alloc, $student, $school, $enrollment, $user, $level, $section) {
            $alloc->ensureAdmissionNumber($student, $school);
            $alloc->allocateForEnrollment($enrollment, $student->fresh(), $school, $user, ['class_level_id' => $level->id, 'class_section_id' => $section->id]);
            throw new \RuntimeException('force outer rollback');
        });
    } catch (\RuntimeException $e) {
        $thrown = $e->getMessage() === 'force outer rollback';
    }
    expect($thrown)->toBeTrue();
    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBe(0);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(0);
    $student->refresh();
    expect($student->admission_number)->toBeNull();
});

it('serializes concurrent admission number assignment on the same student', function () {
    if (!p5DriverSupportsConcurrentConnections() || !function_exists('pcntl_fork')) {
        $this->markTestSkipped('Requires pgsql/mysql + pcntl_fork for multi-process concurrency');
    }
    $school = p5School();
    $student = p5Student($school);
    $tmp = tempnam(sys_get_temp_dir(), 'p5adm');
    file_put_contents($tmp, json_encode(['school_id' => $school->id, 'student_id' => $student->id]));
    $pids = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('fork failed');
        }
        if ($pid === 0) {
            DB::reconnect();
            $data = json_decode(file_get_contents($tmp), true);
            $school = School::query()->findOrFail($data['school_id']);
            $student = Student::query()->findOrFail($data['student_id']);
            $alloc = new PlacementAllocationService(new RegistrationNumberService(), new StudentPlacementService());
            try {
                $number = $alloc->ensureAdmissionNumber($student, $school);
                file_put_contents($tmp . '.out' . $i, $number);
                exit(0);
            } catch (Throwable $e) {
                file_put_contents($tmp . '.out' . $i, 'ERR:' . $e->getMessage());
                exit(1);
            }
        }
        $pids[] = $pid;
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
    $a = @file_get_contents($tmp . '.out0');
    $b = @file_get_contents($tmp . '.out1');
    expect($a)->not->toBeEmpty()->and($b)->not->toBeEmpty()->and($a)->toBe($b)->and(str_starts_with((string) $a, 'ERR:'))->toBeFalse();
    $student->refresh();
    expect($student->admission_number)->toBe($a);
})->group('concurrency');

it('serializes concurrent registration assignment for the same student to a single current number', function () {
    if (!p5DriverSupportsConcurrentConnections() || !function_exists('pcntl_fork')) {
        $this->markTestSkipped('Requires pgsql/mysql + pcntl_fork for multi-process concurrency');
    }
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $tmp = tempnam(sys_get_temp_dir(), 'p5reg');
    file_put_contents($tmp, json_encode(['school_id' => $school->id, 'user_id' => $user->id, 'student_id' => $student->id, 'session_id' => $session->id, 'level_id' => $level->id, 'section_id' => $section->id]));
    $pids = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('fork failed');
        }
        if ($pid === 0) {
            DB::reconnect();
            $data = json_decode(file_get_contents($tmp), true);
            $school = School::query()->findOrFail($data['school_id']);
            $user = User::query()->findOrFail($data['user_id']);
            $student = Student::query()->findOrFail($data['student_id']);
            $reg = new RegistrationNumberService();
            try {
                $number = $reg->assign($student, $school, ['academic_session_id' => $data['session_id'], 'class_level_id' => $data['level_id'], 'class_section_id' => $data['section_id']], RegistrationNumberService::REASON_INITIAL, $user);
                file_put_contents($tmp . '.out' . $i, $number);
                exit(0);
            } catch (Throwable $e) {
                file_put_contents($tmp . '.out' . $i, 'ERR:' . $e->getMessage());
                exit(1);
            }
        }
        $pids[] = $pid;
    }
    $codes = [];
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wexitstatus($status);
    }
    expect($codes)->toBe([0, 0]);
    $a = @file_get_contents($tmp . '.out0');
    $b = @file_get_contents($tmp . '.out1');
    expect($a)->not->toBeEmpty()->and($b)->not->toBeEmpty()
        ->and(str_starts_with((string) $a, 'ERR:'))->toBeFalse()
        ->and(str_starts_with((string) $b, 'ERR:'))->toBeFalse()
        ->and($a)->toBe($b);
    $assignments = DB::table('registration_number_assignments')->where('school_id', $school->id)->where('student_id', $student->id)->get();
    expect($assignments)->toHaveCount(1)->and($assignments->first()->registration_number)->toBe($a);
})->group('concurrency');

it('rejects ensureAdmissionNumber when student belongs to a different school', function () {
    $schoolA = p5School('A', 'AAA');
    $schoolB = p5School('B', 'BBB');
    $student = p5Student($schoolA);
    ['alloc' => $alloc] = p5Services();
    expect(fn() => $alloc->ensureAdmissionNumber($student, $schoolB))->toThrow(ValidationException::class);
    expect($student->fresh()->admission_number)->toBeNull();
});

it('rejects ensureAdmissionNumber for wrong school even when student already has an admission number', function () {
    $schoolA = p5School('A', 'AAA');
    $schoolB = p5School('B', 'BBB');
    $student = p5Student($schoolA);
    ['alloc' => $alloc] = p5Services();
    $number = $alloc->ensureAdmissionNumber($student, $schoolA);
    expect(fn() => $alloc->ensureAdmissionNumber($student->fresh(), $schoolB))->toThrow(ValidationException::class);
    expect($student->fresh()->admission_number)->toBe($number);
});

it('rejects assign when student belongs to a different school', function () {
    $schoolA = p5School('A', 'AAA');
    $schoolB = p5School('B', 'BBB');
    $user = p5User();
    $session = p5Session($schoolB);
    $level = p5Level($schoolB);
    $section = p5Section($schoolB, $level, 'A', 30, 10);
    $student = p5Student($schoolA);
    ['reg' => $reg] = p5Services();
    expect(fn() => $reg->assign($student, $schoolB, [
        'academic_session_id' => $session->id,
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ], RegistrationNumberService::REASON_INITIAL, $user))->toThrow(ValidationException::class);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(0);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('rolls back direct RegistrationNumberService::assign when the outer caller aborts', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    ['reg' => $reg] = p5Services();
    $thrown = false;
    try {
        DB::transaction(function () use ($reg, $student, $school, $session, $level, $section, $user) {
            $reg->assign($student, $school, [
                'academic_session_id' => $session->id,
                'class_level_id' => $level->id,
                'class_section_id' => $section->id,
            ], RegistrationNumberService::REASON_INITIAL, $user);
            throw new \RuntimeException('force outer rollback after direct assign');
        });
    } catch (\RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'force outer rollback');
    }
    expect($thrown)->toBeTrue();
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(0);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('keeps legacy identifier types on the cache counter path when id_sequences exists', function () {
    // Phase 5 table is present (built by beforeEach). Legacy types must not write to it.
    $school = p5School();
    $before = DB::table('id_sequences')->count();

    $a = IdGenerator::generate('staff_id', $school);
    $b = IdGenerator::generate('staff_id', $school);
    $c = IdGenerator::generate('application', $school);

    expect($a)->not->toBe($b)
        ->and($a)->not->toBeEmpty()
        ->and($c)->not->toBeEmpty();
    expect(DB::table('id_sequences')->count())->toBe($before);
    expect(DB::table('id_sequences')->where('type', 'staff_id')->count())->toBe(0);
    expect(DB::table('id_sequences')->where('type', 'application')->count())->toBe(0);
});

it('preserves the exact pre-Phase-5 cache key for legacy identifier types', function () {
    // Phase 4 key: id_counter:{type}:{schoolId}:{year} — no scopeKey segment.
    $school = p5School();
    $year = (int) now()->year;
    $legacyKey = "id_counter:staff_id:{$school->id}:{$year}";
    $phase5StyleKey = "id_counter:staff_id:{$school->id}::{$year}"; // extra empty scope segment

    Cache::put($legacyKey, 41, now()->addYears(10));
    Cache::forget($phase5StyleKey);

    $generated = IdGenerator::generate('staff_id', $school);

    // Must continue from the seeded Phase 4 key (42), not restart at 1 under a new key.
    expect(Cache::get($legacyKey))->toBe(42);
    expect(Cache::get($phase5StyleKey))->toBeNull();
    expect($generated)->toContain('042'); // sequence_length default 6 → padded 000042 → pattern may vary
    expect(DB::table('id_sequences')->where('type', 'staff_id')->count())->toBe(0);
});

it('still requires id_sequences for Phase 5 types and uses the DB path when present', function () {
    $school = p5School();
    $before = DB::table('id_sequences')->where('type', 'admission_number')->count();
    IdGenerator::generate('admission_number', $school);
    expect(DB::table('id_sequences')->where('type', 'admission_number')->count())->toBeGreaterThan($before);
});
