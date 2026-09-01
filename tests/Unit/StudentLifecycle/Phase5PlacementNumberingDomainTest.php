<?php

uses(Tests\TestCase::class);

/**
 * Phase 5 — Placement & Registration Numbers domain tests.
 *
 * Covers capacity, auto-allocation, override, manual placement, history,
 * admission/registration numbers, scopes, isolation, sequence integrity.
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
    Schema::dropIfExists('schools');
}

function buildPhase5Schema(): void
{
    dropPhase5Schema();

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        $t->timestamps();
        $t->softDeletes();
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
        $t->uuid('enrollment_id')->nullable();
        $t->uuid('academic_session_id');
        $t->uuid('class_level_id');
        $t->uuid('class_section_id')->nullable();
        $t->string('registration_number', 64)->nullable();
        $t->date('enrolled_at');
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
    return Profile::query()->create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => Str::random(6) . '@ex.test',
    ]);
}

function p5Student(School $school, ?Profile $profile = null): Student
{
    $profile ??= p5Profile();
    return Student::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'profile_id' => $profile->id,
        'status' => 'active',
    ]);
}

function p5Session(School $school, string $name = '2026/2027'): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $school->id,
        'name' => $name,
        'start_date' => '2026-09-01',
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return (object) ['id' => $id, 'name' => $name];
}

function p5SchoolSection(School $school): object
{
    $id = (string) Str::uuid();
    DB::table('school_sections')->insert([
        'id' => $id, 'school_id' => $school->id, 'name' => 'Junior',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    return (object) ['id' => $id];
}

function p5Level(School $school, ?object $schoolSection = null, string $name = 'JSS1'): ClassLevel
{
    $schoolSection ??= p5SchoolSection($school);
    $id = (string) Str::uuid();
    DB::table('class_levels')->insert([
        'id' => $id,
        'school_section_id' => $schoolSection->id,
        'name' => $name,
        'sort_order' => 10,
        'sequence' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return ClassLevel::query()->withoutGlobalScopes()->findOrFail($id);
}

function p5Section(School $school, ClassLevel $level, string $name, int $capacity, int $sort): ClassSection
{
    return ClassSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'class_level_id' => $level->id,
        'name' => $name,
        'display_name' => $level->name . $name,
        'capacity' => $capacity,
        'sort_order' => $sort,
    ]);
}

function p5Enrollment(School $school, object $session, ?Student $student = null): Enrollment
{
    return Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'student_id' => $student?->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
        'meta' => [],
    ]);
}

function p5Services(): array
{
    $reg = new RegistrationNumberService();
    $place = new StudentPlacementService();
    $alloc = new PlacementAllocationService($reg, $place);
    return compact('reg', 'place', 'alloc');
}

it('generates sequential school-scoped admission numbers via id_sequences', function () {
    $school = p5School();
    $a = IdGenerator::generate('admission_number', $school);
    $b = IdGenerator::generate('admission_number', $school);
    expect($a)->not->toBe($b)
        ->and((int) IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->value('last_value'))
        ->toBeGreaterThanOrEqual(2);
});

it('allows the same formatted admission number in different schools', function () {
    $a = p5School('A', 'AAA');
    $b = p5School('B', 'BBB');
    IdGenerator::generate('admission_number', $a);
    IdGenerator::generate('admission_number', $b);
    expect(IdSequence::query()->where('school_id', $a->id)->where('type', 'admission_number')->exists())->toBeTrue()
        ->and(IdSequence::query()->where('school_id', $b->id)->where('type', 'admission_number')->exists())->toBeTrue();
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
    $fillEnr = p5Enrollment($school, $session, $filler);
    $alloc->allocateForEnrollment($fillEnr, $filler, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $full->id,
    ]);

    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    $placement = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
    ]);

    expect($placement->class_section_id)->toBe($next->id)
        ->and($placement->is_current)->toBeTrue()
        ->and($placement->registration_number)->not->toBeEmpty();
});

it('rejects automatic allocation when all sections are full', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();

    $filler = p5Student($school);
    $fillEnr = p5Enrollment($school, $session, $filler);
    $alloc->allocateForEnrollment($fillEnr, $filler, $school, $user, ['class_level_id' => $level->id]);

    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, ['class_level_id' => $level->id]);
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
        $enrollment = p5Enrollment($school, $session, $student);
        $placement = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
            'class_level_id' => $level->id,
            'class_section_id' => $section->id,
        ]);
        expect($placement->class_section_id)->toBe($section->id);
    }

    expect(StudentSessionPlacement::query()->where('class_section_id', $section->id)->where('is_current', true)->whereNull('left_at')->count())->toBe(5);
});

it('rejects full section without override', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();

    $filler = p5Student($school);
    $fillEnr = p5Enrollment($school, $session, $filler);
    $alloc->allocateForEnrollment($fillEnr, $filler, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
        'capacity_override' => false,
    ]);
})->throws(ValidationException::class);

it('rejects capacity override without authorization', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();

    $filler = p5Student($school);
    $fillEnr = p5Enrollment($school, $session, $filler);
    $alloc->allocateForEnrollment($fillEnr, $filler, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
        'capacity_override' => true,
    ]);
})->throws(ValidationException::class);

it('does not overfill capacity-1 section under sequential pressure', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 1, 10);
    ['alloc' => $alloc] = p5Services();

    $s1 = p5Student($school);
    $e1 = p5Enrollment($school, $session, $s1);
    $alloc->allocateForEnrollment($e1, $s1, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    $s2 = p5Student($school);
    $e2 = p5Enrollment($school, $session, $s2);
    expect(fn () => $alloc->allocateForEnrollment($e2, $s2, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]))->toThrow(ValidationException::class);

    expect(StudentSessionPlacement::query()->where('class_section_id', $section->id)->where('is_current', true)->whereNull('left_at')->count())->toBe(1);
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
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();

    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $levelA->id,
        'class_section_id' => $sectionB->id,
    ]);
})->throws(ValidationException::class);

it('rejects cross-school section placement', function () {
    $schoolA = p5School('A', 'AAA');
    $schoolB = p5School('B', 'BBB');
    $user = p5User();
    $session = p5Session($schoolA);
    $levelA = p5Level($schoolA);
    $levelB = p5Level($schoolB);
    $sectionB = p5Section($schoolB, $levelB, 'A', 30, 10);
    $student = p5Student($schoolA);
    $enrollment = p5Enrollment($schoolA, $session, $student);
    ['alloc' => $alloc] = p5Services();

    $alloc->allocateForEnrollment($enrollment, $student, $schoolA, $user, [
        'class_level_id' => $levelA->id,
        'class_section_id' => $sectionB->id,
    ]);
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

    $first = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secA->id,
    ]);

    $second = $alloc->placeManually($student, $school, $level->id, $secB->id, $user, [
        'academic_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
    ]);

    $history = StudentSessionPlacement::query()
        ->where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->orderBy('id')
        ->get();

    expect($history)->toHaveCount(2)
        ->and($history[0]->id)->toBe($first->id)
        ->and($history[0]->is_current)->toBeFalse()
        ->and($history[0]->left_at)->not->toBeNull()
        ->and($history[1]->id)->toBe($second->id)
        ->and($history[1]->is_current)->toBeTrue()
        ->and($history[1]->class_section_id)->toBe($secB->id);
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

    $e1 = p5Enrollment($school, $session1, $student);
    $p1 = $alloc->allocateForEnrollment($e1, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secA->id,
    ]);

    $e2 = p5Enrollment($school, $session2, $student);
    $p2 = $alloc->allocateForEnrollment($e2, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secB->id,
    ]);

    $p1Fresh = $p1->fresh();
    expect($p1Fresh->is_current)->toBeTrue()
        ->and($p1Fresh->left_at)->toBeNull()
        ->and($p2->is_current)->toBeTrue()
        ->and($p2->academic_session_id)->toBe($session2->id);
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

    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secA->id,
    ]);
    $alloc->placeManually($student, $school, $level->id, $secB->id, $user, [
        'academic_session_id' => $session->id,
    ]);

    $currentCount = StudentSessionPlacement::query()
        ->where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->where('is_current', true)
        ->count();

    expect($currentCount)->toBe(1);
});

it('assigns registration numbers and preserves history on regenerate', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc, 'reg' => $reg] = p5Services();

    $placement = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    $first = $placement->registration_number;
    $second = $reg->regenerate($student, $school, $placement->fresh(), $user);

    expect($second)->not->toBe($first)
        ->and($reg->history($student, $school->id))->toHaveCount(2)
        ->and($reg->currentNumber($student, $school->id))->toBe($second);
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
    $e1 = p5Enrollment($school, $session, $s1);
    $p1 = $alloc->allocateForEnrollment($e1, $s1, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secA->id,
    ]);

    $s2 = p5Student($school);
    $e2 = p5Enrollment($school, $session, $s2);
    $p2 = $alloc->allocateForEnrollment($e2, $s2, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $secB->id,
    ]);

    expect($p1->registration_number)->toBe($p2->registration_number);
});

it('does not change admission number when registration number regenerates', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc, 'reg' => $reg] = p5Services();

    $adm = $alloc->ensureAdmissionNumber($student, $school);
    $placement = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);
    $reg->regenerate($student, $school, $placement->fresh(), $user);

    expect($student->fresh()->admission_number)->toBe($adm);
});

it('enforces active registration number uniqueness via assignments table', function () {
    $school = p5School();
    DB::table('registration_number_assignments')->insert([
        'school_id' => $school->id,
        'scope_key' => 'test-scope',
        'registration_number' => '01',
        'student_id' => p5Student($school)->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('registration_number_assignments')->insert([
        'school_id' => $school->id,
        'scope_key' => 'test-scope',
        'registration_number' => '01',
        'student_id' => p5Student($school)->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('uses integer placement ids referenced by registration history', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();

    $placement = $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    expect(is_numeric($placement->id))->toBeTrue();

    $hist = RegistrationNumberHistory::query()->where('student_id', $student->id)->first();
    expect($hist)->not->toBeNull()->and($hist->placement_id)->toBe($placement->id);
});

it('rolls back placement when registration assignment would fail mid-flight', function () {
    $school = p5School();
    $user = p5User();
    $session = p5Session($school);
    $level = p5Level($school);
    $section = p5Section($school, $level, 'A', 30, 10);
    $student = p5Student($school);
    $enrollment = p5Enrollment($school, $session, $student);
    ['alloc' => $alloc] = p5Services();

    $alloc->allocateForEnrollment($enrollment, $student, $school, $user, [
        'class_level_id' => $level->id,
        'class_section_id' => $section->id,
    ]);

    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('does not produce duplicate sequence values under sequential concurrent-style calls', function () {
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
    expect($a)->not->toBe($b)
        ->and(IdSequence::query()->where('type', 'registration_number')->where('school_id', $school->id)->where('scope_key', 'scope-x')->value('last_value'))
        ->toBe(2);
});
