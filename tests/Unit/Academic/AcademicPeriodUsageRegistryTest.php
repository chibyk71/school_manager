<?php

/**
 * Phase 4: academic period dependency registry domain tests.
 * Minimal schema (no full RefreshDatabase) — matches other Academic unit tests.
 */

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Contracts\Academic\TracksAcademicUsage as TracksAcademicUsageContract;
use App\Models\Academic\AcademicPeriodUsage;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Services\Academic\AcademicPeriodUsageRegistry;
use App\Services\Academic\AcademicSessionLifecycleService;
use App\Services\Academic\RegistryAcademicSessionOperationalData;
use App\Services\Academic\RegistryTermOperationalData;
use App\Services\Academic\TermLifecycleService;
use App\States\Academic\AcademicSession\Draft as SessionDraft;
use App\States\Academic\AcademicSession\Planned as SessionPlanned;
use App\States\Academic\Term\Planned as TermPlanned;
use App\Traits\TracksAcademicUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    foreach ([
        'academic_period_usages',
        'admissions',
        'enrollments',
        'student_applications',
        'student_session_placements',
        'students',
        'terms',
        'academic_sessions',
        'schools',
        'activity_log',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('slug')->nullable();
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // School uses Spatie LogsActivity — required when makeSchool() saves.
    Schema::create('activity_log', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description');
        $table->nullableUuidMorphs('subject', 'subject');
        $table->nullableUuidMorphs('causer', 'causer');
        $table->json('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->string('event')->nullable();
        $table->timestamps();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default(SessionDraft::$name);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('terms', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('academic_session_id');
        $table->string('name');
        $table->string('short_name')->nullable();
        $table->unsignedInteger('ordinal_number')->default(1);
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default(TermPlanned::$name);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('academic_period_usages', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id');
        $table->uuid('term_id')->nullable();
        $table->string('resource_type', 191);
        $table->string('resource_id', 36);
        $table->timestamps();
        $table->unique(['school_id', 'resource_type', 'resource_id'], 'apu_school_resource_unique');
        $table->index(['school_id', 'academic_session_id'], 'apu_school_session_idx');
        $table->index(['school_id', 'term_id'], 'apu_school_term_idx');
    });

    Schema::create('student_applications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->string('status')->default('draft');
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('admissions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->string('status')->default('offered');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->uuid('student_id')->nullable();
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('students', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('student_session_placements', function (Blueprint $table) {
        $table->id();
        $table->uuid('student_id');
        $table->uuid('academic_session_id');
        $table->uuid('class_level_id')->nullable();
        $table->boolean('is_current')->default(true);
        $table->timestamps();
    });

    $this->app->bind(AcademicSessionOperationalDataBoundary::class, RegistryAcademicSessionOperationalData::class);
    $this->app->bind(TermOperationalDataBoundary::class, RegistryTermOperationalData::class);
    $this->app->singleton(AcademicPeriodUsageRegistry::class);
});

function makeSchool(string $name = 'School A'): School
{
    $school = new School;
    $school->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
    ])->save();

    return $school->fresh();
}

function makeSession(School $school, string $name = '2026/2027'): AcademicSession
{
    $session = new AcademicSession;
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => $name,
        'start_date' => '2026-09-01',
        'end_date' => '2027-07-31',
        'state' => SessionDraft::$name,
    ])->save();

    return $session->fresh();
}

function makeTerm(AcademicSession $session, string $name = 'Term 1', int $ordinal = 1): Term
{
    $term = new Term;
    $term->forceFill([
        'id' => (string) Str::uuid(),
        'academic_session_id' => $session->id,
        'name' => $name,
        'ordinal_number' => $ordinal,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-15',
        'state' => TermPlanned::$name,
    ])->save();

    return $term->fresh();
}

it('registers and unregisters a resource idempotently', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $registry = app(AcademicPeriodUsageRegistry::class);

    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'submitted',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect(AcademicPeriodUsage::query()->count())->toBe(1);
    expect($registry->hasSessionDependencies($session))->toBeTrue();

    $registry->register($app);
    expect(AcademicPeriodUsage::query()->count())->toBe(1);

    $app->delete();
    expect(AcademicPeriodUsage::query()->count())->toBe(0);
    expect($registry->hasSessionDependencies($session))->toBeFalse();

    $app->restore();
    expect(AcademicPeriodUsage::query()->count())->toBe(1);
});

it('rejects cross-school session registration', function () {
    $schoolA = makeSchool('A');
    $schoolB = makeSchool('B');
    $sessionB = makeSession($schoolB);

    $app = new StudentApplication;
    $app->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionB->id,
        'status' => 'draft',
    ]);

    expect(fn () => app(AcademicPeriodUsageRegistry::class)->register($app))
        ->toThrow(ValidationException::class);
});

it('session dependency query includes term-level rows', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $term = makeTerm($session);
    $registry = app(AcademicPeriodUsageRegistry::class);

    $resource = new class extends Model implements TracksAcademicUsageContract
    {
        use SoftDeletes;
        use TracksAcademicUsage;

        protected $table = 'student_applications';
        public $incrementing = false;
        protected $keyType = 'string';
        protected $guarded = [];

        public function academicUsageSchoolId(): ?string
        {
            return (string) $this->school_id;
        }

        public function academicUsageSessionId(): ?string
        {
            return (string) $this->academic_session_id;
        }

        public function academicUsageTermId(): ?string
        {
            return $this->attributes['term_id'] ?? null;
        }
    };

    $resource->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'draft',
    ]);
    DB::table('student_applications')->insert([
        'id' => $resource->id,
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $resource->exists = true;
    $resource->setAttribute('term_id', $term->id);

    $registry->register($resource);

    expect($registry->hasTermDependencies($term))->toBeTrue();
    expect($registry->hasSessionDependencies($session))->toBeTrue();
    expect($registry->getSessionDependencies($session))->toHaveCount(1);
    expect($registry->getTermDependencies($term))->toHaveCount(1);
});

it('session lifecycle start-date lock uses registry boundary', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $session->forceFill(['state' => SessionPlanned::$name])->save();

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'submitted',
        'first_name' => 'A',
        'last_name' => 'B',
    ]);

    $lifecycle = app(AcademicSessionLifecycleService::class);

    expect(fn () => $lifecycle->updateDates($session, '2026-10-01', null))
        ->toThrow(ValidationException::class);
});

it('term delete is blocked when registry has term usage', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $term = makeTerm($session);
    $registry = app(AcademicPeriodUsageRegistry::class);

    AcademicPeriodUsage::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'term_id' => $term->id,
        'resource_type' => 'fake',
        'resource_id' => (string) Str::uuid(),
    ]);

    expect($registry->hasTermDependencies($term))->toBeTrue();

    $boundary = app(TermOperationalDataBoundary::class);
    expect($boundary->hasOperationalData($term))->toBeTrue();
});

it('session delete is blocked when dependencies exist', function () {
    $school = makeSchool();
    $session = makeSession($school);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'submitted',
        'first_name' => 'X',
        'last_name' => 'Y',
    ]);

    $lifecycle = app(AcademicSessionLifecycleService::class);

    expect(fn () => $lifecycle->delete($session))->toThrow(ValidationException::class);
});

it('session delete is blocked while terms exist independent of registry', function () {
    $school = makeSchool();
    $session = makeSession($school);
    makeTerm($session);

    expect(app(AcademicPeriodUsageRegistry::class)->hasSessionDependencies($session))->toBeFalse();

    $lifecycle = app(AcademicSessionLifecycleService::class);
    expect(fn () => $lifecycle->delete($session))->toThrow(ValidationException::class);
});

it('force delete of application removes dependency', function () {
    $school = makeSchool();
    $session = makeSession($school);

    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'draft',
        'first_name' => 'F',
        'last_name' => 'D',
    ]);

    expect(AcademicPeriodUsage::query()->count())->toBe(1);
    $app->forceDelete();
    expect(AcademicPeriodUsage::query()->count())->toBe(0);
});

it('admission and enrollment create register session-level usage', function () {
    $school = makeSchool();
    $session = makeSession($school);

    Admission::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'offered',
    ]);

    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'status' => 'draft',
    ]);

    expect(AcademicPeriodUsage::query()->count())->toBe(2);
    expect(app(AcademicPeriodUsageRegistry::class)->hasSessionDependencies($session))->toBeTrue();
});

it('cross-school isolation: school B session does not see school A usage', function () {
    $schoolA = makeSchool('A');
    $schoolB = makeSchool('B');
    $sessionA = makeSession($schoolA, 'A-26');
    $sessionB = makeSession($schoolB, 'B-26');

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'status' => 'submitted',
        'first_name' => 'A',
        'last_name' => 'A',
    ]);

    $registry = app(AcademicPeriodUsageRegistry::class);
    expect($registry->hasSessionDependencies($sessionA))->toBeTrue();
    expect($registry->hasSessionDependencies($sessionB))->toBeFalse();
});

it('TermLifecycleService lockSessionTerms acquires school before session (source protocol)', function () {
    $path = base_path('app/Services/Academic/TermLifecycleService.php');
    $src = file_get_contents($path);
    $methodStart = strpos($src, 'function lockSessionTerms');
    expect($methodStart)->not->toBeFalse();
    $method = substr($src, $methodStart, 900);

    $schoolLock = strpos($method, 'AcademicPeriodLock::lockSchool');
    $sessionLock = strpos($method, 'lockForUpdate');
    expect($schoolLock)->not->toBeFalse();
    expect($sessionLock)->not->toBeFalse();
    expect($schoolLock < $sessionLock)->toBeTrue();

    $firstSessionQuery = strpos($method, 'AcademicSession::query()');
    expect($firstSessionQuery)->not->toBeFalse();
    $between = substr($method, $firstSessionQuery, $schoolLock - $firstSessionQuery);
    expect($between)->not->toContain('lockForUpdate');
});

it('AcademicSessionLifecycleService lockSchoolSessions acquires school before sessions (source protocol)', function () {
    $path = base_path('app/Services/Academic/AcademicSessionLifecycleService.php');
    $src = file_get_contents($path);
    $methodStart = strpos($src, 'function lockSchoolSessions');
    expect($methodStart)->not->toBeFalse();
    $method = substr($src, $methodStart, 600);

    $schoolLock = strpos($method, 'AcademicPeriodLock::lockSchool');
    $sessionLock = strpos($method, 'AcademicSession::query()');
    expect($schoolLock)->not->toBeFalse();
    expect($sessionLock)->not->toBeFalse();
    expect($schoolLock < $sessionLock)->toBeTrue();
});
