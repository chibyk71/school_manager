<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — dashboard counts must be academic-session scoped for placement/capacity.
 */

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildDashSchema();
});

afterEach(function () {
    dropDashSchema();
});

function dropDashSchema(): void
{
    foreach ([
        'student_session_placements',
        'enrollments',
        'students',
        'class_sections',
        'academic_sessions',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildDashSchema(): void
{
    dropDashSchema();

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('slug')->unique();
        $t->string('code')->nullable();
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->boolean('is_current')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('class_sections', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name')->nullable();
        $t->unsignedInteger('capacity')->default(0);
        $t->timestamps();
    });

    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('status')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('student_id')->nullable();
        $t->string('status');
        $t->timestamp('activated_at')->nullable();
        $t->timestamps();
    });

    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id')->nullable();
        $t->uuid('student_id');
        $t->uuid('academic_session_id');
        $t->uuid('class_section_id')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->boolean('is_current')->default(true);
        $t->timestamp('enrolled_at')->nullable();
        $t->timestamps();
    });
}

function dashSchool(string $name): School
{
    $s = new School;
    $s->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => Str::upper(Str::random(4)),
    ])->save();

    return $s->fresh();
}

function dashSession(School $school, string $name, bool $current = false): AcademicSession
{
    $s = new AcademicSession;
    $s->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => $name,
        'is_current' => $current,
    ])->save();

    return $s->fresh();
}

it('counts active enrollment as awaiting placement when only prior-session placement exists', function () {
    $school = dashSchool('DashSchool');
    $sessionA = dashSession($school, '2025/2026', false);
    $sessionB = dashSession($school, '2026/2027', true);

    $student = new Student;
    $student->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'status' => 'active',
    ])->save();

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $sessionB->id,
        'student_id' => $student->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    StudentSessionPlacement::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'student_id' => $student->id,
        'academic_session_id' => $sessionA->id,
        'is_current' => true,
        'enrolled_at' => now()->subYear(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($school, $sessionB);

    expect($counts['awaiting_placement'])->toBe(1);
});

it('does not count prior-session placement toward current-session section capacity', function () {
    $school = dashSchool('CapSchool');
    $sessionA = dashSession($school, '2025/2026', false);
    $sessionB = dashSession($school, '2026/2027', true);

    $section = ClassSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'JSS1A',
        'capacity' => 10,
    ]);

    // Fill 9/10 with prior-session current placements — would look near capacity if not session-scoped
    for ($i = 0; $i < 9; $i++) {
        $st = new Student;
        $st->forceFill([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'status' => 'active',
        ])->save();

        StudentSessionPlacement::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'student_id' => $st->id,
            'academic_session_id' => $sessionA->id,
            'class_section_id' => $section->id,
            'is_current' => true,
            'enrolled_at' => now()->subYear(),
        ]);
    }

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($school, $sessionB);

    expect($counts['sections_near_capacity'])->toBe(0);

    for ($i = 0; $i < 9; $i++) {
        $st = new Student;
        $st->forceFill([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'status' => 'active',
        ])->save();

        StudentSessionPlacement::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'student_id' => $st->id,
            'academic_session_id' => $sessionB->id,
            'class_section_id' => $section->id,
            'is_current' => true,
            'enrolled_at' => now(),
        ]);
    }

    $countsB = $ops->dashboardCounts($school, $sessionB);
    expect($countsB['sections_near_capacity'])->toBe(1);
});

it('dashboard counts remain school-scoped', function () {
    $schoolA = dashSchool('SchoolA');
    $schoolB = dashSchool('SchoolB');
    $sessionA = dashSession($schoolA, '2026/2027', true);
    $sessionB = dashSession($schoolB, '2026/2027', true);

    $studentB = new Student;
    $studentB->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'status' => 'active',
    ])->save();

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'student_id' => $studentB->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($schoolA, $sessionA);

    expect($counts['awaiting_placement'])->toBe(0);
});
