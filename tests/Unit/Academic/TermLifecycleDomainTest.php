<?php

/**
 * Phase 3 Term lifecycle domain tests.
 *
 * Minimal-schema pattern (same as AcademicSessionLifecycleTest).
 */

use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Events\Academic\TermCreated;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\Services\Academic\AcademicSessionLifecycleService;
use App\Services\Academic\NullTermOperationalData;
use App\Services\Academic\TermLifecycleService;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Closed as SessionClosed;
use App\States\Academic\AcademicSession\Draft as SessionDraft;
use App\States\Academic\AcademicSession\Paused as SessionPaused;
use App\States\Academic\AcademicSession\Planned as SessionPlanned;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosed;
use App\States\Academic\Term\Planned as TermPlanned;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->app->bind(TermOperationalDataBoundary::class, NullTermOperationalData::class);

    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('terms');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
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
    $this->school->forceFill(['id' => $schoolId, 'name' => 'Test School'])->save();

    $this->session = new AcademicSession();
    $this->session->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolId,
        'name' => '2026/2027',
        'start_date' => '2026-09-01',
        'end_date' => '2027-07-31',
        'state' => SessionActive::$name,
    ])->save();

    $this->terms = app(TermLifecycleService::class);
    $this->sessions = app(AcademicSessionLifecycleService::class);
});

function makeTerm(array $overrides = []): Term
{
    $session = $overrides['session'] ?? test()->session;
    unset($overrides['session']);

    $defaults = [
        'id' => (string) Str::uuid(),
        'academic_session_id' => $session->id,
        'name' => 'Term ' . Str::random(4),
        'ordinal_number' => (int) (Term::where('academic_session_id', $session->id)->max('ordinal_number') ?? 0) + 1,
        'state' => TermPlanned::$name,
        'start_date' => null,
        'end_date' => null,
    ];

    $term = new Term();
    $term->forceFill(array_merge($defaults, $overrides))->save();

    return $term->fresh();
}

it('creates a PLANNED term with sequence 1 for the first term', function () {
    Event::fake([TermCreated::class]);
    $term = $this->terms->create($this->session, ['name' => 'First Term']);
    expect($term->state)->toBeInstanceOf(TermPlanned::class)
        ->and($term->ordinal_number)->toBe(1)
        ->and($term->name)->toBe('First Term')
        ->and($term->start_date)->toBeNull();
    Event::assertDispatched(TermCreated::class);
});

it('appends subsequent terms with contiguous sequence', function () {
    $this->terms->create($this->session, ['name' => 'First']);
    $t2 = $this->terms->create($this->session, ['name' => 'Second']);
    $t3 = $this->terms->create($this->session, ['name' => 'Third']);
    expect($t2->ordinal_number)->toBe(2)->and($t3->ordinal_number)->toBe(3);
});

it('activates PLANNED → ACTIVE when session is ACTIVE and dates valid', function () {
    Event::fake([TermActivated::class]);
    $term = makeTerm(['name' => 'First', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
    $activated = $this->terms->activate($term);
    expect($activated->state)->toBeInstanceOf(TermActive::class);
    Event::assertDispatched(TermActivated::class);
});

it('rejects activation when session is not ACTIVE', function () {
    foreach ([SessionDraft::$name, SessionPlanned::$name, SessionPaused::$name, SessionClosed::$name] as $state) {
        $this->session->forceFill(['state' => $state])->save();
        $term = makeTerm([
            'name' => 'T-' . $state,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'ordinal_number' => Term::where('academic_session_id', $this->session->id)->max('ordinal_number') + 1,
        ]);
        expect(fn () => $this->terms->activate($term))->toThrow(ValidationException::class);
    }
    $this->session->forceFill(['state' => SessionActive::$name])->save();
});

it('rejects activation without dates', function () {
    $term = makeTerm(['name' => 'No Dates']);
    expect(fn () => $this->terms->activate($term))->toThrow(ValidationException::class);
});

it('rejects overlapping term activation', function () {
    makeTerm(['name' => 'Existing', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'state' => TermClosed::$name]);
    $overlap = makeTerm(['name' => 'Overlap', 'start_date' => '2026-11-01', 'end_date' => '2027-03-01']);
    expect(fn () => $this->terms->activate($overlap))->toThrow(ValidationException::class);
});

it('allows adjacent term dates', function () {
    makeTerm(['name' => 'T1', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'state' => TermClosed::$name]);
    $adjacent = makeTerm(['name' => 'T2', 'start_date' => '2026-12-15', 'end_date' => '2027-03-31']);
    $result = $this->terms->activate($adjacent);
    expect($result->state)->toBeInstanceOf(TermActive::class);
});

it('rejects second ACTIVE term in the same session', function () {
    $t1 = makeTerm(['name' => 'First', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
    $this->terms->activate($t1);
    $t2 = makeTerm(['name' => 'Second', 'start_date' => '2026-12-15', 'end_date' => '2027-03-31']);
    expect(fn () => $this->terms->activate($t2))->toThrow(ValidationException::class);
});

it('closes ACTIVE → CLOSED without activating another term', function () {
    Event::fake([TermClosed::class]);
    $t1 = makeTerm(['name' => 'First', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
    $this->terms->activate($t1);
    $t2 = makeTerm(['name' => 'Second', 'start_date' => '2026-12-15', 'end_date' => '2027-03-31']);
    $closed = $this->terms->close($t1->fresh());
    expect($closed->state)->toBeInstanceOf(TermClosed::class)
        ->and($closed->closed_at)->not->toBeNull()
        ->and($t2->fresh()->state)->toBeInstanceOf(TermPlanned::class);
    Event::assertDispatched(TermClosed::class);
});

it('rejects closing a PLANNED term', function () {
    $term = makeTerm(['name' => 'Planned']);
    expect(fn () => $this->terms->close($term))->toThrow(ValidationException::class);
});

it('rejects session close while an ACTIVE term exists', function () {
    $term = makeTerm(['name' => 'Active Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
    $this->terms->activate($term);
    expect(fn () => $this->sessions->close($this->session->fresh()))->toThrow(ValidationException::class);
});

it('allows session close after all terms are CLOSED', function () {
    $term = makeTerm(['name' => 'Active Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
    $this->terms->activate($term);
    $this->terms->close($term->fresh());
    $closed = $this->sessions->close($this->session->fresh());
    expect($closed->state)->toBeInstanceOf(SessionClosed::class);
});

it('normalizes sequence after deleting a middle term', function () {
    $t1 = $this->terms->create($this->session, ['name' => 'First']);
    $t2 = $this->terms->create($this->session, ['name' => 'Second']);
    $t3 = $this->terms->create($this->session, ['name' => 'Third']);
    $this->terms->delete($t2);
    expect($t1->fresh()->ordinal_number)->toBe(1)
        ->and($t3->fresh()->ordinal_number)->toBe(2)
        ->and(Term::where('academic_session_id', $this->session->id)->count())->toBe(2);
});

it('does not allow CLOSED → ACTIVE via reopen path', function () {
    $term = makeTerm([
        'name' => 'Closed',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-15',
        'state' => TermClosed::$name,
        'closed_at' => now(),
    ]);
    expect(fn () => app(\App\Services\AcademicCalendarService::class)->reopenTerm($term))
        ->toThrow(ValidationException::class);
    expect($term->fresh()->state)->toBeInstanceOf(TermClosed::class);
});
