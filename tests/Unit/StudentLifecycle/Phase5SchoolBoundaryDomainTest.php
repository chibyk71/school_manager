<?php

uses(Tests\TestCase::class);

/**
 * Phase 5 school-boundary and admission DB-immutability tests.
 */

use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\RegistrationNumberHistory;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
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
    p5bDrop();
    p5bBuild();
});

afterEach(fn () => p5bDrop());

function p5bDrop(): void
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

function p5bBuild(): void
{
    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->string('name'); $t->string('code')->nullable(); $t->timestamps(); $t->softDeletes();
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
        $t->uuid('id')->primary(); $t->uuid('school_id'); $t->uuid('profile_id'); $t->string('admission_number')->nullable(); $t->date('admission_date')->nullable(); $t->string('status', 50)->default('active'); $t->timestamps(); $t->softDeletes();
        $t->unique(['school_id', 'profile_id']); $t->unique(['school_id', 'admission_number']);
    });
    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary(); $t->uuid('student_id')->nullable(); $t->uuid('school_id'); $t->uuid('academic_session_id'); $t->uuid('admission_id')->nullable(); $t->string('status', 40)->default('draft'); $t->timestamp('activated_at')->nullable(); $t->json('meta')->nullable(); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->id(); $t->uuid('student_id'); $t->uuid('enrollment_id')->nullable(); $t->uuid('academic_session_id'); $t->uuid('class_level_id'); $t->uuid('class_section_id')->nullable(); $t->string('registration_number', 64)->nullable(); $t->date('enrolled_at'); $t->date('left_at')->nullable(); $t->boolean('is_current')->default(false); $t->string('promotion_outcome', 50)->nullable(); $t->unsignedBigInteger('promotion_batch_id')->nullable(); $t->text('notes')->nullable(); $t->boolean('capacity_override_used')->default(false); $t->uuid('placed_by')->nullable(); $t->json('meta')->nullable(); $t->timestamps();
    });
    Schema::create('registration_number_histories', function (Blueprint $t) {
        $t->id(); $t->uuid('student_id'); $t->uuid('school_id'); $t->uuid('enrollment_id')->nullable(); $t->unsignedBigInteger('placement_id')->nullable(); $t->string('registration_number', 64); $t->string('scope_key', 191)->nullable(); $t->uuid('academic_session_id')->nullable(); $t->uuid('class_level_id')->nullable(); $t->uuid('class_section_id')->nullable(); $t->string('reason', 64)->nullable(); $t->timestamp('effective_from'); $t->timestamp('effective_to')->nullable(); $t->uuid('assigned_by')->nullable(); $t->json('meta')->nullable(); $t->timestamps();
    });
    Schema::create('registration_number_assignments', function (Blueprint $t) {
        $t->id(); $t->uuid('school_id'); $t->string('scope_key', 191); $t->string('registration_number', 64); $t->uuid('student_id'); $t->unsignedBigInteger('history_id')->nullable(); $t->timestamps();
        $t->unique(['school_id', 'scope_key', 'registration_number']); $t->unique(['school_id', 'student_id']);
    });
    Schema::create('id_sequences', function (Blueprint $t) {
        $t->id(); $t->string('type', 64); $t->uuid('school_id')->nullable(); $t->string('scope_key', 191)->default(''); $t->unsignedInteger('year')->default(0); $t->unsignedBigInteger('last_value')->default(0); $t->timestamps();
        $t->unique(['type', 'school_id', 'scope_key', 'year']);
    });
    DB::unprepared('DROP TRIGGER IF EXISTS trg_students_admission_number_immutable');
    DB::unprepared("CREATE TRIGGER trg_students_admission_number_immutable BEFORE UPDATE ON students FOR EACH ROW WHEN OLD.admission_number IS NOT NULL AND NEW.admission_number IS NOT OLD.admission_number BEGIN SELECT RAISE(ABORT, 'admission_number is immutable once assigned'); END;");
}

function p5bSchool(string $n = 'A', string $c = 'AAA'): School
{
    return School::query()->create(['id' => (string) Str::uuid(), 'name' => $n, 'code' => $c]);
}
function p5bUser()
{
    return \App\Models\User::query()->create(['id' => (string) Str::uuid(), 'name' => 'S', 'email' => Str::random(6).'@t.local']);
}
function p5bStudent(School $s)
{
    $p = \App\Models\Profile::query()->create(['id' => (string) Str::uuid(), 'first_name' => 'A', 'last_name' => 'B', 'email' => Str::random(6).'@e.t']);
    return Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $s->id, 'profile_id' => $p->id, 'status' => 'active']);
}
function p5bSession(School $s, string $n = '2026/2027')
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert(['id' => $id, 'school_id' => $s->id, 'name' => $n, 'start_date' => '2026-09-01', 'is_current' => true, 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id];
}
function p5bLevel(School $s)
{
    $ss = (string) Str::uuid();
    DB::table('school_sections')->insert(['id' => $ss, 'school_id' => $s->id, 'name' => 'J', 'created_at' => now(), 'updated_at' => now()]);
    $id = (string) Str::uuid();
    DB::table('class_levels')->insert(['id' => $id, 'school_section_id' => $ss, 'name' => 'JSS1', 'sort_order' => 10, 'sequence' => 10, 'created_at' => now(), 'updated_at' => now()]);
    return \App\Models\Academic\ClassLevel::query()->withoutGlobalScopes()->findOrFail($id);
}
function p5bSection(School $s, $level, string $name = 'A')
{
    return \App\Models\Academic\ClassSection::query()->create(['id' => (string) Str::uuid(), 'school_id' => $s->id, 'class_level_id' => $level->id, 'name' => $name, 'capacity' => 30, 'sort_order' => 10]);
}
function p5bServices()
{
    $reg = new RegistrationNumberService();
    return ['reg' => $reg, 'alloc' => new PlacementAllocationService($reg, new StudentPlacementService())];
}

it('rejects allocateForEnrollment when enrollment session belongs to another school', function () {
    $a = p5bSchool('A', 'AAA'); $b = p5bSchool('B', 'BBB'); $user = p5bUser();
    $sessionB = p5bSession($b); $levelA = p5bLevel($a); p5bSection($a, $levelA);
    $student = p5bStudent($a);
    $enrollment = Enrollment::query()->create([
        'id' => (string) Str::uuid(), 'school_id' => $a->id, 'academic_session_id' => $sessionB->id,
        'student_id' => $student->id, 'status' => Enrollment::STATUS_ACTIVE, 'activated_at' => now(), 'meta' => [],
    ]);
    ['alloc' => $alloc] = p5bServices();
    expect(fn () => $alloc->allocateForEnrollment($enrollment, $student, $a, $user, ['class_level_id' => $levelA->id]))
        ->toThrow(ValidationException::class);
    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('rejects placeManually when academic session belongs to another school', function () {
    $a = p5bSchool('A', 'AAA'); $b = p5bSchool('B', 'BBB'); $user = p5bUser();
    $sessionB = p5bSession($b); $levelA = p5bLevel($a); $secA = p5bSection($a, $levelA);
    $student = p5bStudent($a); ['alloc' => $alloc] = p5bServices();
    expect(fn () => $alloc->placeManually($student, $a, $levelA->id, $secA->id, $user, ['academic_session_id' => $sessionB->id]))
        ->toThrow(ValidationException::class);
    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('rejects registration assign when academic session belongs to another school', function () {
    $a = p5bSchool('A', 'AAA'); $b = p5bSchool('B', 'BBB'); $user = p5bUser();
    $sessionB = p5bSession($b); $levelA = p5bLevel($a); $secA = p5bSection($a, $levelA);
    $student = p5bStudent($a); ['reg' => $reg] = p5bServices();
    expect(fn () => $reg->assign($student, $a, [
        'academic_session_id' => $sessionB->id, 'class_level_id' => $levelA->id, 'class_section_id' => $secA->id,
    ], RegistrationNumberService::REASON_INITIAL, $user))->toThrow(ValidationException::class);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(0);
    expect(RegistrationNumberHistory::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('rejects registration assign when class section belongs to another school', function () {
    $a = p5bSchool('A', 'AAA'); $b = p5bSchool('B', 'BBB'); $user = p5bUser();
    $sessionA = p5bSession($a); $levelA = p5bLevel($a); $levelB = p5bLevel($b); $secB = p5bSection($b, $levelB);
    $student = p5bStudent($a); ['reg' => $reg] = p5bServices();
    expect(fn () => $reg->assign($student, $a, [
        'academic_session_id' => $sessionA->id, 'class_level_id' => $levelA->id, 'class_section_id' => $secB->id,
    ], RegistrationNumberService::REASON_INITIAL, $user))->toThrow(ValidationException::class);
    expect(DB::table('registration_number_assignments')->where('student_id', $student->id)->count())->toBe(0);
});

it('prevents query-builder mutation of an assigned admission number at the database', function () {
    $school = p5bSchool(); $student = p5bStudent($school); ['alloc' => $alloc] = p5bServices();
    $number = $alloc->ensureAdmissionNumber($student, $school);
    expect(fn () => DB::table('students')->where('id', $student->id)->update(['admission_number' => 'HACKED-RAW']))
        ->toThrow(\Throwable::class);
    expect($student->fresh()->admission_number)->toBe($number);
});
