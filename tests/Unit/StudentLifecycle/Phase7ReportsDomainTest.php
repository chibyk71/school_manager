<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — lifecycle report filters, placement completeness, school isolation.
 */

use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Services\Student\LifecycleOperationalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase7ReportsSchema();
});

afterEach(function () {
    dropPhase7ReportsSchema();
});

function dropPhase7ReportsSchema(): void
{
    foreach ([
        'student_session_placements',
        'class_sections',
        'class_levels',
        'enrollments',
        'admissions',
        'student_applications',
        'academic_sessions',
        'schools',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildPhase7ReportsSchema(): void
{
    dropPhase7ReportsSchema();

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->timestamps();
    });

    Schema::create('class_levels', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('name');
        $t->timestamps();
    });

    Schema::create('class_sections', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('class_level_id')->nullable();
        $t->string('name');
        $t->unsignedInteger('capacity')->default(0);
        $t->timestamps();
    });

    Schema::create('student_applications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->string('status');
        $t->string('source')->nullable();
        $t->string('application_number')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->timestamp('submitted_at')->nullable();
        $t->timestamps();
    });

    Schema::create('admissions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('class_level_id')->nullable();
        $t->uuid('application_id')->nullable();
        $t->string('status');
        $t->string('admission_number')->nullable();
        $t->timestamp('offered_at')->nullable();
        $t->timestamp('accepted_at')->nullable();
        $t->timestamp('acceptance_deadline')->nullable();
        $t->timestamps();
    });

    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('academic_session_id')->nullable();
        $t->uuid('admission_id')->nullable();
        $t->uuid('student_id')->nullable();
        $t->string('status');
        $t->timestamp('activated_at')->nullable();
        $t->timestamps();
    });

    Schema::create('student_session_placements', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('student_id')->nullable();
        $t->uuid('class_section_id');
        $t->uuid('class_level_id')->nullable();
        $t->uuid('academic_session_id')->nullable();
        $t->boolean('is_current')->default(true);
        $t->timestamps();
    });
}

function p7rSchool(string $name): School
{
    $s = new School;
    $s->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => Str::upper(Str::random(4)),
    ])->save();

    return $s->fresh();
}

function p7rSession(School $school): object
{
    $id = (string) Str::uuid();
    \DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $school->id,
        'name' => '2026/2027',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id, 'school_id' => $school->id];
}

it('application status filter changes report totals', function () {
    $school = p7rSchool('FilterApp');
    $session = p7rSession($school);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'application_number' => 'APP-1',
        'submitted_at' => now(),
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_APPROVED,
        'application_number' => 'APP-2',
        'submitted_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $all = $ops->applicationReport($school, []);
    $approvedOnly = $ops->applicationReport($school, ['status' => StudentApplication::STATUS_APPROVED]);

    expect($all['total'])->toBe(2)
        ->and($approvedOnly['total'])->toBe(1)
        ->and($approvedOnly['approved'])->toBe(1);
});

it('admission origin filter distinguishes application vs direct', function () {
    $school = p7rSchool('FilterAdm');
    $session = p7rSession($school);
    $appId = (string) Str::uuid();

    StudentApplication::query()->create([
        'id' => $appId,
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_APPROVED,
        'application_number' => 'APP-X',
        'submitted_at' => now(),
    ]);

    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'application_id' => $appId,
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
    ]);
    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'application_id' => null,
        'status' => Admission::STATUS_OFFERED,
        'offered_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    expect($ops->admissionsQuery($school, ['origin' => 'application'])->count())->toBe(1);
    expect($ops->admissionsQuery($school, ['origin' => 'direct'])->count())->toBe(1);

    $directReport = $ops->admissionReport($school, ['origin' => 'direct']);
    $byStatusTotal = array_sum($directReport['by_status'] ?? []);
    expect($byStatusTotal)->toBe(1);
});

it('enrollment finalized filter changes result set', function () {
    $school = p7rSchool('FilterEnr');
    $session = p7rSession($school);

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_DRAFT,
    ]);
    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => Enrollment::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    expect($ops->enrollmentsQuery($school, ['finalized' => true])->count())->toBe(1);
    expect($ops->enrollmentsQuery($school, ['finalized' => false])->count())->toBe(1);

    $finalizedReport = $ops->enrollmentReport($school, ['finalized' => true]);
    expect($finalizedReport['total'])->toBe(1)
        ->and($finalizedReport['finalized'])->toBe(1);
});

it('placement report does not silently truncate beyond 50 sections', function () {
    $school = p7rSchool('PlacementMany');
    $session = p7rSession($school);
    $levelId = (string) Str::uuid();
    \DB::table('class_levels')->insert([
        'id' => $levelId,
        'school_id' => $school->id,
        'name' => 'JSS1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sectionCount = 55;
    for ($i = 0; $i < $sectionCount; $i++) {
        $sectionId = (string) Str::uuid();
        \DB::table('class_sections')->insert([
            'id' => $sectionId,
            'school_id' => $school->id,
            'class_level_id' => $levelId,
            'name' => 'Sec '.($i + 1),
            'capacity' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($i < 10) {
            \DB::table('student_session_placements')->insert([
                'id' => (string) Str::uuid(),
                'student_id' => (string) Str::uuid(),
                'class_section_id' => $sectionId,
                'class_level_id' => $levelId,
                'academic_session_id' => $session->id,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $ops = app(LifecycleOperationalService::class);
    $report = $ops->placementReport($school, ['academic_session_id' => $session->id]);

    expect($report['sections_with_capacity'])->toBe($sectionCount)
        ->and(count($report['section_utilization']))->toBe($sectionCount)
        ->and($report['current_placements'])->toBe(10);

    $withPlaced = collect($report['section_utilization'])->where('placed', '>', 0)->count();
    expect($withPlaced)->toBe(10);
});

it('placement and application reports remain school-scoped', function () {
    $schoolA = p7rSchool('IsoA');
    $schoolB = p7rSchool('IsoB');
    $sessionA = p7rSession($schoolA);
    $sessionB = p7rSession($schoolB);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $levelA = (string) Str::uuid();
    \DB::table('class_levels')->insert([
        'id' => $levelA,
        'school_id' => $schoolA->id,
        'name' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \DB::table('class_sections')->insert([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'class_level_id' => $levelA,
        'name' => 'A1',
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $levelB = (string) Str::uuid();
    \DB::table('class_levels')->insert([
        'id' => $levelB,
        'school_id' => $schoolB->id,
        'name' => 'B',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \DB::table('class_sections')->insert([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'class_level_id' => $levelB,
        'name' => 'B1',
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    expect($ops->applicationReport($schoolA, [])['total'])->toBe(1);
    expect($ops->placementReport($schoolA, [])['sections_with_capacity'])->toBe(1);
    expect($ops->placementReport($schoolB, [])['sections_with_capacity'])->toBe(1);
});

it('applicationsQuery filters match applicationReport filters', function () {
    $school = p7rSchool('Parity');
    $session = p7rSession($school);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_SUBMITTED,
        'source' => 'online',
        'submitted_at' => now()->subDays(2),
    ]);
    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => StudentApplication::STATUS_APPROVED,
        'source' => 'walk_in',
        'submitted_at' => now(),
    ]);

    $ops = app(LifecycleOperationalService::class);
    $filters = ['source' => 'online'];
    expect($ops->applicationsQuery($school, $filters)->count())
        ->toBe($ops->applicationReport($school, $filters)['total']);
});
