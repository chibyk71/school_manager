<?php

uses(Tests\TestCase::class);

/**
 * Phase 5 focused regressions: assign idempotency + IdGenerator resetCounter year alignment.
 */

use App\Helpers\IdGenerator;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\IdSequence;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\RegistrationNumberHistory;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Student\RegistrationNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    p5iDrop();
    p5iBuild();
});

afterEach(fn () => p5iDrop());

function p5iDrop(): void
{
    Schema::dropIfExists('registration_number_assignments');
    Schema::dropIfExists('registration_number_histories');
    Schema::dropIfExists('student_session_placements');
    Schema::dropIfExists('class_sections');
    Schema::dropIfExists('class_levels');
    Schema::dropIfExists('school_sections');
    Schema::dropIfExists('students');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::dropIfExists('id_sequences');
    Schema::dropIfExists('settings');
    Schema::dropIfExists('schools');
}

function p5iBuild(): void
{
    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        $t->string('slug')->nullable();
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
        $t->uuid('id')->primary(); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->timestamps();
    });
    Schema::create('profiles', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('school_id'); $t->string('name'); $t->date('start_date')->nullable(); $t->boolean('is_current')->default(false); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('school_sections', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('school_id'); $t->string('name'); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('class_levels', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('school_section_id'); $t->string('name'); $t->integer('sort_order')->default(0); $t->integer('sequence')->default(0); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('class_sections', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('school_id'); $t->uuid('class_level_id'); $t->string('name'); $t->string('display_name')->nullable(); $t->integer('capacity')->default(0); $t->integer('sort_order')->default(0); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('school_id'); $t->uuid('profile_id'); $t->string('admission_number')->nullable(); $t->string('status', 50)->default('active'); $t->timestamps(); $t->softDeletes();
        $t->unique(['school_id', 'profile_id']);
    });
    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->id(); $t->uuid('student_id'); $t->uuid('academic_session_id'); $t->uuid('class_level_id'); $t->uuid('class_section_id')->nullable(); $t->string('registration_number', 64)->nullable(); $t->date('enrolled_at'); $t->boolean('is_current')->default(false); $t->timestamps();
    });
    Schema::create('registration_number_histories', function (Blueprint $t) {
        $t->id(); $t->uuid('student_id'); $t->uuid('school_id'); $t->uuid('enrollment_id')->nullable(); $t->unsignedBigInteger('placement_id')->nullable(); $t->string('registration_number', 64); $t->string('scope_key', 191)->nullable(); $t->uuid('academic_session_id')->nullable(); $t->uuid('class_level_id')->nullable(); $t->uuid('class_section_id')->nullable(); $t->string('reason', 64)->nullable(); $t->timestamp('effective_from'); $t->timestamp('effective_to')->nullable(); $t->uuid('assigned_by')->nullable(); $t->json('meta')->nullable(); $t->timestamps();
    });
    Schema::create('registration_number_assignments', function (Blueprint $t) {
        $t->id(); $t->uuid('school_id'); $t->string('scope_key', 191); $t->string('registration_number', 64); $t->uuid('student_id'); $t->unsignedBigInteger('history_id')->nullable(); $t->timestamps();
        $t->unique(['school_id', 'scope_key', 'registration_number'], 'uq_regnum_assignment_active');
        $t->unique(['school_id', 'student_id'], 'uq_regnum_assignment_student');
    });
    Schema::create('id_sequences', function (Blueprint $t) {
        $t->id(); $t->string('type', 64); $t->uuid('school_id')->nullable(); $t->string('scope_key', 191)->default(''); $t->unsignedInteger('year')->default(0); $t->unsignedBigInteger('last_value')->default(0); $t->timestamps();
        $t->unique(['type', 'school_id', 'scope_key', 'year'], 'uq_id_sequences_scope');
    });
}

function p5iSchool(): School
{
    return School::query()->create(['id' => (string) Str::uuid(), 'name' => 'Alpha', 'code' => 'ALP']);
}
function p5iUser(): User
{
    return User::query()->create(['id' => (string) Str::uuid(), 'name' => 'Staff', 'email' => Str::random(8).'@t.local']);
}
function p5iStudent(School $school): Student
{
    $p = Profile::query()->create(['id' => (string) Str::uuid(), 'first_name' => 'A', 'last_name' => 'B', 'email' => Str::random(6).'@e.t']);
    return Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'profile_id' => $p->id, 'status' => 'active']);
}
function p5iSession(School $school, string $name = '2026/2027'): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert(['id' => $id, 'school_id' => $school->id, 'name' => $name, 'start_date' => '2026-09-01', 'is_current' => true, 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id];
}
function p5iLevel(School $school): ClassLevel
{
    $ss = (string) Str::uuid();
    DB::table('school_sections')->insert(['id' => $ss, 'school_id' => $school->id, 'name' => 'J', 'created_at' => now(), 'updated_at' => now()]);
    $id = (string) Str::uuid();
    DB::table('class_levels')->insert(['id' => $id, 'school_section_id' => $ss, 'name' => 'JSS1', 'sort_order' => 10, 'sequence' => 10, 'created_at' => now(), 'updated_at' => now()]);
    return ClassLevel::query()->withoutGlobalScopes()->findOrFail($id);
}
function p5iSection(School $school, ClassLevel $level, string $name = 'A'): ClassSection
{
    return ClassSection::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'class_level_id' => $level->id, 'name' => $name, 'capacity' => 30, 'sort_order' => 10]);
}
function p5iReg(): RegistrationNumberService
{
    return new RegistrationNumberService();
}

it('returns the same registration number on repeated assign for the same student and context', function () {
    $school = p5iSchool(); $user = p5iUser(); $session = p5iSession($school); $level = p5iLevel($school);
    $section = p5iSection($school, $level, 'A'); $student = p5iStudent($school);
    $reg = p5iReg();
    $ctx = ['academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $section->id];

    $first = $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    $second = $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);

    expect($second)->toBe($first);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(1);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->whereNull('effective_to')->count())->toBe(1);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('does not create duplicate history when re-assigning the same logical context', function () {
    $school = p5iSchool(); $user = p5iUser(); $session = p5iSession($school); $level = p5iLevel($school);
    $section = p5iSection($school, $level, 'A'); $student = p5iStudent($school);
    $reg = p5iReg();
    $ctx = ['academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $section->id];

    $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);

    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(1);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('releases and reassigns when the registration context (scope) changes', function () {
    // Default scope is school_session_section — each section has its own sequence, so the
    // formatted number may legitimately restart at "01". What must change is the active
    // assignment's scope_key and history (previous closed, new current).
    $school = p5iSchool(); $user = p5iUser(); $session = p5iSession($school); $level = p5iLevel($school);
    $secA = p5iSection($school, $level, 'A'); $secB = p5iSection($school, $level, 'B');
    $student = p5iStudent($school); $reg = p5iReg();

    $scopeA = $reg->buildScopeKey($school, $session->id, $level->id, $secA->id);
    $scopeB = $reg->buildScopeKey($school, $session->id, $level->id, $secB->id);
    expect($scopeA)->not->toBe($scopeB);

    $first = $reg->assign($student, $school, [
        'academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $secA->id,
    ], RegistrationNumberService::REASON_INITIAL, $user);

    $second = $reg->assign($student, $school, [
        'academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $secB->id,
    ], RegistrationNumberService::REASON_SECTION_CHANGE, $user);

    $current = DB::table('registration_number_assignments')->where('student_id', $student->id)->first();
    expect($current)->not->toBeNull();
    expect($current->scope_key)->toBe($scopeB);
    expect($current->registration_number)->toBe($second);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(1);

    $histories = RegistrationNumberHistory::query()->where('student_id', $student->id)->orderBy('id')->get();
    expect($histories)->toHaveCount(2);
    expect($histories[0]->scope_key)->toBe($scopeA);
    expect($histories[0]->registration_number)->toBe($first);
    expect($histories[0]->effective_to)->not->toBeNull();
    expect($histories[1]->scope_key)->toBe($scopeB);
    expect($histories[1]->registration_number)->toBe($second);
    expect($histories[1]->effective_to)->toBeNull();
});

it('explicit regenerate forces a new registration number for the same context', function () {
    $school = p5iSchool(); $user = p5iUser(); $session = p5iSession($school); $level = p5iLevel($school);
    $section = p5iSection($school, $level, 'A'); $student = p5iStudent($school);
    $reg = p5iReg();
    $ctx = ['academic_session_id' => $session->id, 'class_level_id' => $level->id, 'class_section_id' => $section->id];

    $first = $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_INITIAL, $user);
    $second = $reg->assign($student, $school, $ctx, RegistrationNumberService::REASON_REGENERATE, $user);

    expect($second)->not->toBe($first);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->value('registration_number'))->toBe($second);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('resetCounter without year targets the same Phase 5 sequence that generate uses', function () {
    $school = p5iSchool();
    $year = (int) now()->year;

    IdGenerator::generate('admission_number', $school);
    expect((int) IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('scope_key', '')->where('year', $year)->value('last_value'))->toBeGreaterThan(0);

    IdGenerator::resetCounter('admission_number', $school);

    expect(IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('scope_key', '')->where('year', $year)->exists())->toBeFalse();
    expect(IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('year', 0)->exists())->toBeFalse();

    IdGenerator::generate('admission_number', $school);
    expect((int) IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('scope_key', '')->where('year', $year)->value('last_value'))->toBe(1);
});

it('resetCounter with an explicit year only targets that Phase 5 sequence', function () {
    $school = p5iSchool();
    $current = (int) now()->year;
    $other = $current - 1;

    IdGenerator::generate('admission_number', $school, $current);
    IdGenerator::generate('admission_number', $school, $other);

    IdGenerator::resetCounter('admission_number', $school, $other);

    expect(IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('year', $other)->exists())->toBeFalse();
    expect(IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->where('year', $current)->exists())->toBeTrue();
});

it('legacy resetCounter without year still uses current-year cache key', function () {
    $school = p5iSchool();
    $year = (int) now()->year;
    $legacyKey = "id_counter:staff_id:{$school->id}:{$year}";

    Cache::put($legacyKey, 7, now()->addYears(10));
    IdGenerator::resetCounter('staff_id', $school);

    expect(Cache::get($legacyKey))->toBeNull();
    IdGenerator::generate('staff_id', $school);
    expect(Cache::get($legacyKey))->toBe(1);
});
