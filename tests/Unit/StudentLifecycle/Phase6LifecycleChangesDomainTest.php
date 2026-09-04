<?php

uses(Tests\TestCase::class);

/**
 * Phase 6 — Lifecycle Changes domain tests.
 * Section/class change, transfer/withdrawal, promotion placement integration.
 *
 * Helpers are prefixed p6* (not p5*) so this file does not collide with
 * Phase5PlacementNumberingDomainTest when Pest loads the suite.
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
use App\Services\Student\StudentStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase6Schema();
});

afterEach(function () {
    dropPhase6Schema();
});

function dropPhase6Schema(): void
{
    foreach ([
        'registration_number_assignments', 'registration_number_histories',
        'student_session_placements', 'student_class_section_pivot',
        'class_sections', 'class_levels', 'school_sections', 'enrollments',
        'students', 'academic_sessions', 'profiles', 'users', 'id_sequences',
        'permission_role', 'permission_user', 'role_user', 'permissions', 'roles',
        'settings', 'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildPhase6Schema(): void
{
    dropPhase6Schema();
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
        // Written by StudentStatusService (withdraw/transfer/graduate/etc.)
        $t->string('status_reason')->nullable();
        $t->date('status_date')->nullable();
        $t->date('status_until')->nullable();
        $t->uuid('status_changed_by')->nullable();
        $t->string('transfer_destination')->nullable();
        $t->text('notes')->nullable();
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
        $t->timestamp('withdrawn_at')->nullable();
        $t->timestamp('transferred_out_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->text('notes')->nullable();
        $t->json('meta')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    // Align with Phase 5/6 PlacementAllocationService create payload:
    // school_id + joined_at are written by placeForPromotionOutcome / createPlacement.
    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->id();
        $t->uuid('student_id');
        $t->uuid('school_id');
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
        $t->index(['school_id', 'academic_session_id', 'is_current']);
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

function p6School(string $name = 'Alpha', string $code = 'ALP'): School
{
    return School::query()->create(['id' => (string) Str::uuid(), 'name' => $name, 'code' => $code]);
}
function p6User(): User
{
    return User::query()->create(['id' => (string) Str::uuid(), 'name' => 'Staff', 'email' => Str::random(8).'@t.local']);
}
function p6Profile(): Profile
{
    return Profile::query()->create(['id' => (string) Str::uuid(), 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => Str::random(6).'@ex.test']);
}
function p6Student(School $school, ?Profile $profile = null): Student
{
    $profile ??= p6Profile();
    return Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'profile_id' => $profile->id, 'status' => 'active']);
}
function p6Session(School $school, string $name = '2026/2027'): object
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert(['id' => $id, 'school_id' => $school->id, 'name' => $name, 'start_date' => '2026-09-01', 'is_current' => true, 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id, 'name' => $name];
}
function p6SchoolSection(School $school): object
{
    $id = (string) Str::uuid();
    DB::table('school_sections')->insert(['id' => $id, 'school_id' => $school->id, 'name' => 'Junior', 'created_at' => now(), 'updated_at' => now()]);
    return (object) ['id' => $id];
}
function p6Level(School $school, ?object $schoolSection = null, string $name = 'JSS1'): ClassLevel
{
    $schoolSection ??= p6SchoolSection($school);
    $id = (string) Str::uuid();
    DB::table('class_levels')->insert(['id' => $id, 'school_section_id' => $schoolSection->id, 'name' => $name, 'sort_order' => 10, 'sequence' => 10, 'created_at' => now(), 'updated_at' => now()]);
    return ClassLevel::query()->withoutGlobalScopes()->findOrFail($id);
}
function p6Section(School $school, ClassLevel $level, string $name, int $capacity, int $sort): ClassSection
{
    return ClassSection::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'class_level_id' => $level->id, 'name' => $name, 'display_name' => $level->name.$name, 'capacity' => $capacity, 'sort_order' => $sort]);
}
function p6Enrollment(School $school, object $session, ?Student $student = null): Enrollment
{
    return Enrollment::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'academic_session_id' => $session->id, 'student_id' => $student?->id, 'status' => Enrollment::STATUS_ACTIVE, 'activated_at' => now(), 'meta' => []]);
}
function p6Services(): array
{
    $reg = new RegistrationNumberService();
    $place = new StudentPlacementService();
    $alloc = new PlacementAllocationService($reg, $place);

    return compact('reg', 'place', 'alloc');
}

it('changes section and preserves placement history', function () {
    $school = p6School();
    $user = p6User();
    $student = p6Student($school);
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 40, 2);
    p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $first = $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, ['academic_session_id' => $session->id]);
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);

    $second = $svc['alloc']->changeSection($student, $school, $secB->id, $user, []);

    expect($second->class_section_id)->toBe($secB->id)
        ->and($second->is_current)->toBeTrue()
        ->and($second->id)->not->toBe($first->id);

    $first->refresh();
    expect($first->is_current)->toBeFalse()->and($first->left_at)->not->toBeNull();

    $student->refresh();
    expect($student->admission_number)->toBe($admission);

    $hist = StudentSessionPlacement::query()
        ->where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->count();
    expect($hist)->toBe(2);
});

it('rejects section change to full section without capacity override', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 1, 2);
    $svc = p6Services();

    $occupant = p6Student($school);
    $svc['alloc']->placeManually($occupant, $school, $level->id, $secB->id, $user, ['academic_session_id' => $session->id]);

    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, ['academic_session_id' => $session->id]);

    expect(fn () => $svc['alloc']->changeSection($student, $school, $secB->id, $user, []))
        ->toThrow(ValidationException::class);
});

it('rejects cross-school section change', function () {
    $school = p6School();
    $other = p6School('Other', 'OTH');
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $otherLevel = p6Level($other);
    $otherSec = p6Section($other, $otherLevel, 'X', 40, 1);
    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, ['academic_session_id' => $session->id]);

    expect(fn () => $svc['alloc']->changeSection($student, $school, $otherSec->id, $user, []))
        ->toThrow(ValidationException::class);
});

it('changes class level within session and keeps admission number', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $ss = p6SchoolSection($school);
    $jss1 = p6Level($school, $ss, 'JSS1');
    $jss2 = p6Level($school, $ss, 'JSS2');
    $sec1 = p6Section($school, $jss1, 'A', 40, 1);
    $sec2 = p6Section($school, $jss2, 'A', 40, 1);
    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $first = $svc['alloc']->placeManually($student, $school, $jss1->id, $sec1->id, $user, ['academic_session_id' => $session->id]);
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);

    $second = $svc['alloc']->changeClass($student, $school, $jss2->id, $sec2->id, $user, []);

    expect($second->class_level_id)->toBe($jss2->id)
        ->and($second->class_section_id)->toBe($sec2->id)
        ->and($second->is_current)->toBeTrue();
    $first->refresh();
    expect($first->is_current)->toBeFalse();
    $student->refresh();
    expect($student->admission_number)->toBe($admission);
});

it('withdrawal ends active enrollment and current placement without deleting history', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $sec = p6Section($school, $level, 'A', 40, 1);
    $student = p6Student($school);
    $enrollment = p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $placement = $svc['alloc']->placeManually($student, $school, $level->id, $sec->id, $user, ['academic_session_id' => $session->id]);
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);

    $status = new StudentStatusService($svc['place']);
    $status->withdraw($student, 'Family relocated', Carbon::parse('2026-10-01'), $user);

    $student->refresh();
    $enrollment->refresh();
    $placement->refresh();

    expect($student->status)->toBe('withdrawn')
        ->and($student->admission_number)->toBe($admission)
        ->and($enrollment->status)->toBe(Enrollment::STATUS_WITHDRAWN)
        ->and($enrollment->withdrawn_at)->not->toBeNull()
        ->and($placement->is_current)->toBeFalse()
        ->and($placement->left_at)->not->toBeNull()
        ->and(Student::query()->whereKey($student->id)->exists())->toBeTrue()
        ->and(Enrollment::query()->whereKey($enrollment->id)->exists())->toBeTrue();
});

it('transfer out ends enrollment as transferred_out and preserves admission number', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $sec = p6Section($school, $level, 'A', 40, 1);
    $student = p6Student($school);
    $enrollment = p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $svc['alloc']->placeManually($student, $school, $level->id, $sec->id, $user, ['academic_session_id' => $session->id]);
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);

    $status = new StudentStatusService($svc['place']);
    $status->transferOut($student, 'Unity High School', 'Parent request', $user, Carbon::parse('2026-11-01'));

    $student->refresh();
    $enrollment->refresh();

    expect($student->status)->toBe('transferred')
        ->and($student->transfer_destination)->toBe('Unity High School')
        ->and($student->admission_number)->toBe($admission)
        ->and($enrollment->status)->toBe(Enrollment::STATUS_TRANSFERRED_OUT)
        ->and($enrollment->transferred_out_at)->not->toBeNull();
});

it('placeForPromotionOutcome creates next-session placement and keeps admission number', function () {
    $school = p6School();
    $user = p6User();
    $session1 = p6Session($school, '2025/2026');
    $id2 = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id2, 'school_id' => $school->id, 'name' => '2026/2027',
        'start_date' => '2026-09-01', 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ss = p6SchoolSection($school);
    $jss1 = p6Level($school, $ss, 'JSS1');
    $jss2 = p6Level($school, $ss, 'JSS2');
    $sec1 = p6Section($school, $jss1, 'A', 40, 1);
    $sec2 = p6Section($school, $jss2, 'A', 40, 1);
    $student = p6Student($school);
    $svc = p6Services();
    $svc['alloc']->placeManually($student, $school, $jss1->id, $sec1->id, $user, ['academic_session_id' => $session1->id]);
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);

    $next = $svc['alloc']->placeForPromotionOutcome(
        $student, $school, $id2, $jss2->id, $sec2->id, $user, 'promoted',
        ['notes' => 'Promoted via test']
    );

    expect($next->academic_session_id)->toBe($id2)
        ->and($next->class_level_id)->toBe($jss2->id)
        ->and($next->is_current)->toBeTrue()
        ->and($next->promotion_outcome)->toBe('promoted');

    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBeGreaterThanOrEqual(2);
    $student->refresh();
    expect($student->admission_number)->toBe($admission);
});

it('rejects section change when student is withdrawn', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 40, 2);
    $student = p6Student($school);
    $student->update(['status' => 'withdrawn']);
    $svc = p6Services();

    StudentSessionPlacement::query()->create([
        'student_id' => $student->id,
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'class_level_id' => $level->id,
        'class_section_id' => $secA->id,
        'joined_at' => now(),
        'enrolled_at' => now()->toDateString(),
        'is_current' => true,
    ]);

    expect(fn () => $svc['alloc']->changeSection($student, $school, $secB->id, $user, []))
        ->toThrow(ValidationException::class);
});

it('preserves registration number history when section change regenerates', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 40, 2);
    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc = p6Services();

    DB::table('settings')->insert([
        'key' => 'academic.registration_number',
        'value' => json_encode(['scope' => 'school_session_section', 'regenerate_on_section_change' => true]),
        'model_type' => School::class,
        'model_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Admission number is independent of placement; establish it explicitly.
    $admission = $svc['alloc']->ensureAdmissionNumber($student, $school);
    expect($admission)->not->toBeNull();

    $first = $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, ['academic_session_id' => $session->id]);
    expect($first->registration_number)->not->toBeNull();

    $svc['alloc']->changeSection($student, $school, $secB->id, $user, []);

    $histCount = RegistrationNumberHistory::query()
        ->where('student_id', $student->id)
        ->where('school_id', $school->id)
        ->count();
    expect($histCount)->toBeGreaterThanOrEqual(1);

    // Section change must not mutate the immutable admission number.
    $student->refresh();
    expect($student->admission_number)->toBe($admission);
});

it('ProcessStudentPromotion always uses placeForPromotionOutcome and never legacy placeOrUpdateInSession', function () {
    $src = file_get_contents(app_path('Jobs/Promotion/ProcessStudentPromotion.php'));
    expect($src)->toContain('placeForPromotionOutcome');
    expect($src)->not->toContain('placeOrUpdateInSession(');
    expect($src)->not->toContain('StudentPlacementService');
});

it('rejects capacity override when actor is not authorized', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 1, 2);
    $svc = p6Services();

    $occupant = p6Student($school);
    $svc['alloc']->placeManually($occupant, $school, $level->id, $secB->id, $user, ['academic_session_id' => $session->id]);

    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, ['academic_session_id' => $session->id]);

    expect(fn () => $svc['alloc']->changeSection($student, $school, $secB->id, $user, [
        'capacity_override' => true,
    ]))->toThrow(ValidationException::class);
});


it('terminal withdraw closes all current session-scoped placements and keeps history', function () {
    $school = p6School();
    $user = p6User();
    $session1 = p6Session($school, '2025/2026');
    $id2 = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id2, 'school_id' => $school->id, 'name' => '2026/2027',
        'start_date' => '2026-09-01', 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $level = p6Level($school);
    $secA = p6Section($school, $level, 'A', 40, 1);
    $secB = p6Section($school, $level, 'B', 40, 2);
    $student = p6Student($school);
    p6Enrollment($school, $session1, $student);
    $svc = p6Services();

    $p1 = $svc['alloc']->placeManually($student, $school, $level->id, $secA->id, $user, [
        'academic_session_id' => $session1->id,
    ]);
    $p2 = $svc['alloc']->placeManually($student, $school, $level->id, $secB->id, $user, [
        'academic_session_id' => $id2,
    ]);

    expect($p1->fresh()->is_current)->toBeTrue()
        ->and($p2->fresh()->is_current)->toBeTrue();

    $status = new StudentStatusService($svc['place']);
    $status->withdraw($student, 'Family relocated across sessions', Carbon::parse('2026-12-01'), $user);

    $p1->refresh();
    $p2->refresh();
    $student->refresh();

    expect($student->status)->toBe('withdrawn')
        ->and($p1->is_current)->toBeFalse()
        ->and($p1->left_at)->not->toBeNull()
        ->and($p2->is_current)->toBeFalse()
        ->and($p2->left_at)->not->toBeNull();

    // Historical rows remain (not deleted).
    expect(StudentSessionPlacement::query()->where('student_id', $student->id)->count())->toBe(2);
    expect(StudentSessionPlacement::query()
        ->where('student_id', $student->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(0);
});

it('changeStatus transfer keeps destination and reason independent', function () {
    $school = p6School();
    $user = p6User();
    $session = p6Session($school);
    $level = p6Level($school);
    $sec = p6Section($school, $level, 'A', 40, 1);
    $student = p6Student($school);
    p6Enrollment($school, $session, $student);
    $svc = p6Services();
    $svc['alloc']->placeManually($student, $school, $level->id, $sec->id, $user, [
        'academic_session_id' => $session->id,
    ]);

    $status = new StudentStatusService($svc['place']);
    $status->changeStatus(
        student: $student,
        newStatus: 'transfer',
        reason: 'Parent request for better STEM programme',
        effectiveDate: Carbon::parse('2026-11-15'),
        changedBy: $user,
        destination: 'Unity High School'
    );

    $student->refresh();

    expect($student->status)->toBe('transferred')
        ->and($student->transfer_destination)->toBe('Unity High School')
        ->and($student->status_reason)->toBe('Parent request for better STEM programme')
        ->and($student->transfer_destination)->not->toBe($student->status_reason);

    expect(StudentSessionPlacement::query()
        ->where('student_id', $student->id)
        ->where('is_current', true)
        ->whereNull('left_at')
        ->count())->toBe(0);
});

it('placeForPromotionOutcome rejects enrollment_id from another school or student', function () {
    $a = p6School('A', 'AAA');
    $b = p6School('B', 'BBB');
    $user = p6User();
    $sessionA = p6Session($a);
    $sessionB = p6Session($b, '2026/2027-B');
    $levelA = p6Level($a);
    $secA = p6Section($a, $levelA, 'A', 40, 1);
    $student = p6Student($a);
    $foreignEnrollment = p6Enrollment($b, $sessionB, null);
    $svc = p6Services();

    $svc['alloc']->placeManually($student, $a, $levelA->id, $secA->id, $user, [
        'academic_session_id' => $sessionA->id,
    ]);

    $nextSessionId = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $nextSessionId, 'school_id' => $a->id, 'name' => '2027/2028',
        'start_date' => '2027-09-01', 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $svc['alloc']->placeForPromotionOutcome(
        $student, $a, $nextSessionId, $levelA->id, $secA->id, $user, 'promoted',
        ['enrollment_id' => $foreignEnrollment->id]
    ))->toThrow(ValidationException::class);
});
