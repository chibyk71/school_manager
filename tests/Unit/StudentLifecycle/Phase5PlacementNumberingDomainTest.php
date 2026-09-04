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
