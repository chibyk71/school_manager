<?php

/**
 * Phase 5 Academic Context API domain tests.
 *
 * Minimal-schema pattern (same as TermLifecycleDomainTest / AcademicSessionLifecycleTest).
 */

use App\Facades\Academic;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\Services\AcademicSessionService;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Closed as SessionClosed;
use App\States\Academic\AcademicSession\Draft as SessionDraft;
use App\States\Academic\AcademicSession\Paused as SessionPaused;
use App\States\Academic\AcademicSession\Planned as SessionPlanned;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosedState;
use App\States\Academic\Term\Planned as TermPlanned;
use App\Support\AcademicContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('terms');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('slug')->nullable();
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default(SessionDraft::$name);
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('terms', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('academic_session_id');
        $table->string('name');
        $table->string('short_name')->nullable();
        $table->unsignedInteger('ordinal_number')->default(1);
        $table->text('description')->nullable();
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default(TermPlanned::$name);
        $table->timestamp('closed_at')->nullable();
        $table->string('color')->nullable();
        $table->json('options')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['academic_session_id', 'ordinal_number'], 'terms_session_ordinal_unique');
    });

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

    $schoolId = (string) Str::uuid();
    $this->school = new School();
    $this->school->forceFill([
        'id' => $schoolId,
        'name' => 'Test School',
        'slug' => 'test-school',
    ]);
    $this->school->exists = true;

    DB::table('schools')->insert([
        'id' => $schoolId,
        'name' => 'Test School',
        'slug' => 'test-school',
        'data' => json_encode(['id' => $schoolId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->app->instance('schoolManager', new class($this->school)
    {
        public function __construct(private School $school) {}

        public function getActiveSchool(): School
        {
            return $this->school;
        }
    });

    $this->app->forgetInstance('academicContext');
    $this->app->singleton('academicContext', fn () => new AcademicSessionService());
    $this->app->alias('academicContext', AcademicSessionService::class);

    Cache::flush();
    $this->svc = app(AcademicSessionService::class);
});

function insertSession(string $schoolId, string $state, string $name = '2025/2026'): AcademicSession
{
    $id = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $id,
        'school_id' => $schoolId,
        'name' => $name,
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-31',
        'state' => $state,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return AcademicSession::query()->findOrFail($id);
}

function insertTerm(string $sessionId, string $state, string $name = 'Term 1', int $ordinal = 1): Term
{
    $id = (string) Str::uuid();
    DB::table('terms')->insert([
        'id' => $id,
        'academic_session_id' => $sessionId,
        'name' => $name,
        'short_name' => 'T'.$ordinal,
        'ordinal_number' => $ordinal,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-15',
        'state' => $state,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Term::query()->findOrFail($id);
}

it('returns ACTIVE session as current', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    expect($this->svc->currentSession()?->id)->toBe($session->id);
});

it('returns PAUSED session as current', function () {
    $session = insertSession($this->school->id, SessionPaused::$name);
    expect($this->svc->currentSession()?->id)->toBe($session->id);
});

it('does not return CLOSED session as current', function () {
    insertSession($this->school->id, SessionClosed::$name);
    expect($this->svc->currentSession())->toBeNull();
});

it('does not return PLANNED session as current', function () {
    insertSession($this->school->id, SessionPlanned::$name);
    expect($this->svc->currentSession())->toBeNull();
});

it('does not return DRAFT session as current', function () {
    insertSession($this->school->id, SessionDraft::$name);
    expect($this->svc->currentSession())->toBeNull();
});

it('returns null when no current session exists', function () {
    expect($this->svc->currentSession())->toBeNull();
});

it('returns ACTIVE term of ACTIVE session', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $term = insertTerm($session->id, TermActive::$name);
    expect($this->svc->currentTerm()?->id)->toBe($term->id);
});

it('returns ACTIVE term of PAUSED session', function () {
    $session = insertSession($this->school->id, SessionPaused::$name);
    $term = insertTerm($session->id, TermActive::$name);
    expect($this->svc->currentTerm()?->id)->toBe($term->id);
});

it('returns null when current session has no ACTIVE term', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    insertTerm($session->id, TermPlanned::$name);
    expect($this->svc->currentTerm())->toBeNull();
});

it('returns null for current term when no current session', function () {
    expect($this->svc->currentTerm())->toBeNull();
});

it('never returns a term belonging to another session', function () {
    $current = insertSession($this->school->id, SessionActive::$name, 'Current');
    insertTerm($current->id, TermPlanned::$name);
    $other = insertSession($this->school->id, SessionClosed::$name, 'Other');
    insertTerm($other->id, TermActive::$name, 'Foreign', 1);
    $this->svc->invalidateCaches($this->school->id);
    expect($this->svc->currentTerm())->toBeNull();
    expect($this->svc->currentSession()?->id)->toBe($current->id);
});

it('builds context for ACTIVE session with active term', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $term = insertTerm($session->id, TermActive::$name);
    $ctx = $this->svc->currentContext();
    expect($ctx)->toBeInstanceOf(AcademicContext::class)
        ->and($ctx->school->id)->toBe($this->school->id)
        ->and($ctx->session->id)->toBe($session->id)
        ->and($ctx->term?->id)->toBe($term->id)
        ->and($ctx->sessionState)->toBe(SessionActive::$name);
});

it('builds context for ACTIVE session without active term', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $ctx = $this->svc->currentContext();
    expect($ctx)->toBeInstanceOf(AcademicContext::class)
        ->and($ctx->session->id)->toBe($session->id)
        ->and($ctx->term)->toBeNull()
        ->and($ctx->sessionState)->toBe(SessionActive::$name);
});

it('builds context for PAUSED session with active term', function () {
    $session = insertSession($this->school->id, SessionPaused::$name);
    $term = insertTerm($session->id, TermActive::$name);
    $ctx = $this->svc->currentContext();
    expect($ctx)->toBeInstanceOf(AcademicContext::class)
        ->and($ctx->session->id)->toBe($session->id)
        ->and($ctx->term?->id)->toBe($term->id)
        ->and($ctx->sessionState)->toBe(SessionPaused::$name);
});

it('builds context for PAUSED session without active term', function () {
    $session = insertSession($this->school->id, SessionPaused::$name);
    $ctx = $this->svc->currentContext();
    expect($ctx)->toBeInstanceOf(AcademicContext::class)
        ->and($ctx->term)->toBeNull()
        ->and($ctx->sessionState)->toBe(SessionPaused::$name);
});

it('returns null context when no current session', function () {
    expect($this->svc->currentContext())->toBeNull();
});

it('requireCurrentSession returns session when present', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    expect($this->svc->requireCurrentSession()->id)->toBe($session->id);
});

it('requireCurrentSession throws when missing', function () {
    expect(fn () => $this->svc->requireCurrentSession())
        ->toThrow(\RuntimeException::class, 'No current operational academic session');
});

it('requireCurrentTerm returns term when present', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $term = insertTerm($session->id, TermActive::$name);
    expect($this->svc->requireCurrentTerm()->id)->toBe($term->id);
});

it('requireCurrentTerm throws when missing', function () {
    insertSession($this->school->id, SessionActive::$name);
    expect(fn () => $this->svc->requireCurrentTerm())
        ->toThrow(\RuntimeException::class, 'No current active academic term');
});

it('requireCurrentContext returns context without requiring term', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $ctx = $this->svc->requireCurrentContext();
    expect($ctx->session->id)->toBe($session->id)->and($ctx->term)->toBeNull();
});

it('requireCurrentContext throws when no session', function () {
    expect(fn () => $this->svc->requireCurrentContext())
        ->toThrow(\RuntimeException::class, 'No current operational academic session');
});

it('session state helpers for ACTIVE', function () {
    insertSession($this->school->id, SessionActive::$name);
    expect($this->svc->sessionState())->toBe(SessionActive::$name)
        ->and($this->svc->isSessionActive())->toBeTrue()
        ->and($this->svc->isSessionPaused())->toBeFalse();
});

it('session state helpers for PAUSED', function () {
    insertSession($this->school->id, SessionPaused::$name);
    expect($this->svc->sessionState())->toBe(SessionPaused::$name)
        ->and($this->svc->isSessionActive())->toBeFalse()
        ->and($this->svc->isSessionPaused())->toBeTrue();
});

it('session state helpers when no current session', function () {
    expect($this->svc->sessionState())->toBeNull()
        ->and($this->svc->isSessionActive())->toBeFalse()
        ->and($this->svc->isSessionPaused())->toBeFalse();
});

it('never resolves another school current session', function () {
    $otherId = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $otherId,
        'name' => 'Other School',
        'slug' => 'other-school',
        'data' => json_encode(['id' => $otherId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    insertSession($otherId, SessionActive::$name, 'Other Active');
    expect($this->svc->currentSession())->toBeNull();
});

it('never resolves another school current term', function () {
    $otherId = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $otherId,
        'name' => 'Other School',
        'slug' => 'other-school-2',
        'data' => json_encode(['id' => $otherId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherSession = insertSession($otherId, SessionActive::$name, 'Other');
    insertTerm($otherSession->id, TermActive::$name);
    expect($this->svc->currentTerm())->toBeNull();
});

it('reflects state after cache invalidation', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    expect($this->svc->currentSession()?->id)->toBe($session->id);
    DB::table('academic_sessions')->where('id', $session->id)->update(['state' => SessionClosed::$name]);
    $this->svc->invalidateCaches($this->school->id);
    expect($this->svc->currentSession())->toBeNull();
});

it('Academic facade delegates to AcademicSessionService', function () {
    $session = insertSession($this->school->id, SessionActive::$name);
    $term = insertTerm($session->id, TermActive::$name);
    expect(Academic::currentSession()?->id)->toBe($session->id)
        ->and(Academic::currentTerm()?->id)->toBe($term->id)
        ->and(Academic::isSessionActive())->toBeTrue()
        ->and(Academic::sessionState())->toBe(SessionActive::$name)
        ->and(Academic::currentContext())->toBeInstanceOf(AcademicContext::class);
});

it('helpers currentSession and currentTerm use academicContext binding', function () {
    $session = insertSession($this->school->id, SessionPaused::$name);
    $term = insertTerm($session->id, TermActive::$name);
    expect(currentSession()?->id)->toBe($session->id)
        ->and(currentTerm()?->id)->toBe($term->id);
});
