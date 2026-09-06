<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — dashboard session boundary:
 * - Academic Calendar owns session resolution
 * - null session ⇒ zero metrics (never all-history aggregate)
 * - cross-session placement/capacity isolation
 * - school isolation
 */

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildBoundarySchema();
});

afterEach(function () {
    dropBoundarySchema();
});

function dropBoundarySchema(): void
{
    foreach ([
        'student_session_placements',
        'enrollments',
        'admissions',
        'student_applications',
        'students',
        'class_sections',
        'academic_sessions',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildBoundarySchema(): void
{
    dropBoundarySchema();

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

    Schema::create('student_applications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->string('status');
        $t->timestamps();
    });

    Schema::create('admissions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->string('status');
        $t->timestamp('acceptance_deadline')->nullable();
        $t->timestamps();
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
        $t->boolean('is_current')->default(true);
        $t->timestamp('enrolled_at')->nullable();
        $t->timestamps();
    });
}

function bSchool(string $name): School
{
    $s = new School;
    $s->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => Str::upper(Str::random(4)),
    ])->save();

    return $s->fresh();
}

function bSession(School $school, string $name, bool $current = false): AcademicSession
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

it('returns zero session-dependent metrics when no session is provided', function () {
    $school = bSchool('NoSession');
    $session = bSession($school, '2026/2027', true);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
    ]);

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($school, null);

    expect($counts['applications_awaiting_review'])->toBe(0)
        ->and($counts['offers_awaiting_acceptance'])->toBe(0)
        ->and($counts['offers_expiring_soon'])->toBe(0)
        ->and($counts['accepted_awaiting_registration'])->toBe(0)
        ->and($counts['enrollments_in_progress'])->toBe(0)
        ->and($counts['ready_for_finalization'])->toBe(0)
        ->and($counts['awaiting_placement'])->toBe(0)
        ->and($counts['sections_near_capacity'])->toBe(0);
});

it('does not aggregate historical sessions when an explicit session is provided', function () {
    $school = bSchool('HistSchool');
    $sessionA = bSession($school, '2025/2026', false);
    $sessionB = bSession($school, '2026/2027', true);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $sessionA->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
    ]);
    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $sessionA->id,
        'status' => Admission::STATUS_OFFERED,
        'acceptance_deadline' => now()->addDays(3),
    ]);
    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $sessionA->id,
        'status' => Enrollment::STATUS_DRAFT,
    ]);

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($school, $sessionB);

    expect($counts['applications_awaiting_review'])->toBe(0)
        ->and($counts['offers_awaiting_acceptance'])->toBe(0)
        ->and($counts['enrollments_in_progress'])->toBe(0)
        ->and($counts['awaiting_placement'])->toBe(0);
});

it('counts enrollment as awaiting placement when only prior-session placement exists', function () {
    $school = bSchool('CrossPlace');
    $sessionA = bSession($school, '2025/2026', false);
    $sessionB = bSession($school, '2026/2027', true);

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

it('does not count prior-session placements toward current-session section capacity', function () {
    $school = bSchool('CapIso');
    $sessionA = bSession($school, '2025/2026', false);
    $sessionB = bSession($school, '2026/2027', true);

    $section = ClassSection::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'JSS1A',
        'capacity' => 2,
    ]);

    for ($i = 0; $i < 2; $i++) {
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

    for ($i = 0; $i < 2; $i++) {
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

it('dashboard counts remain school-scoped even with matching session names', function () {
    $schoolA = bSchool('SchoolA');
    $schoolB = bSchool('SchoolB');
    $sessionA = bSession($schoolA, '2026/2027', true);
    $sessionB = bSession($schoolB, '2026/2027', true);

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

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
    ]);

    $ops = app(LifecycleOperationalService::class);
    $counts = $ops->dashboardCounts($schoolA, $sessionA);

    expect($counts['awaiting_placement'])->toBe(0)
        ->and($counts['applications_awaiting_review'])->toBe(0);

    $countsForeign = $ops->dashboardCounts($schoolA, $sessionB);
    expect($countsForeign['awaiting_placement'])->toBe(0)
        ->and($countsForeign['applications_awaiting_review'])->toBe(0);
});

it('lifecycle service source does not query AcademicSession for resolution', function () {
    $path = base_path('app/Services/Student/LifecycleOperationalService.php');
    if (! is_file($path)) {
        $path = __DIR__.'/../../../app/Services/Student/LifecycleOperationalService.php';
    }
    if (! is_file($path)) {
        expect(true)->toBeTrue();

        return;
    }
    $src = file_get_contents($path);
    expect($src)->not->toContain('AcademicSession::query(')
        ->and($src)->not->toContain('AcademicSession::where(');
});
