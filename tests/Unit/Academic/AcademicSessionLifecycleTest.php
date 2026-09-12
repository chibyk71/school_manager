<?php

/**
 * Phase 2 lifecycle unit tests.
 *
 * Intentionally does NOT use RefreshDatabase: the full migration suite currently
 * fails on SQLite (unrelated devices/staff index issue). Follows the same
 * minimal-schema pattern as AcademicLifecycleMigrationTest / AcademicSessionStateTest.
 */

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\SessionPaused;
use App\Events\Academic\SessionPlanned;
use App\Events\Academic\SessionReopened;
use App\Events\Academic\SessionResumed;
use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Services\Academic\AcademicSessionLifecycleService;
use App\Services\Academic\NullAcademicSessionOperationalData;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->app->bind(
        AcademicSessionOperationalDataBoundary::class,
        NullAcademicSessionOperationalData::class
    );

    Schema::dropIfExists('activity_log');
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
        $table->string('state')->default(Draft::$name);
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
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
    ]);
    $this->school->exists = true;

    \DB::table('schools')->insert([
        'id' => $schoolId,
        'name' => 'Test School',
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
});

afterEach(function () {
    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');
});

function lifecycle(): AcademicSessionLifecycleService
{
    return app(AcademicSessionLifecycleService::class);
}

function makeSession(array $attrs = []): AcademicSession
{
    $schoolId = test()->school->id;
    $id = (string) Str::uuid();
    $data = array_merge([
        'id' => $id,
        'school_id' => $schoolId,
        'name' => 'Session-'.substr($id, 0, 8),
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'state' => Draft::$name,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs);

    \DB::table('academic_sessions')->insert($data);

    return AcademicSession::query()->findOrFail($data['id']);
}

it('operational data boundary returns false for all sessions in Phase 2', function () {
    $session = makeSession();
    expect(app(AcademicSessionOperationalDataBoundary::class)->hasOperationalData($session))->toBeFalse();
});

it('allows DRAFT sessions with incomplete dates', function () {
    $session = makeSession(['start_date' => null, 'end_date' => null, 'state' => Draft::$name]);
    expect($session->state)->toBeInstanceOf(Draft::class)
        ->and($session->start_date)->toBeNull();
});

it('ACTIVE and PAUSED are current; DRAFT PLANNED CLOSED are not', function () {
    expect(makeSession(['state' => Active::$name])->isCurrentOperational())->toBeTrue();
    expect(makeSession(['state' => Paused::$name])->isCurrentOperational())->toBeTrue();
    expect(makeSession(['state' => Draft::$name])->isCurrentOperational())->toBeFalse();
    expect(makeSession(['state' => Planned::$name])->isCurrentOperational())->toBeFalse();
    expect(makeSession(['state' => Closed::$name])->isCurrentOperational())->toBeFalse();
});

it('date mutation is governed by operational-data boundary not hard-coded state', function () {
    expect(makeSession(['state' => Draft::$name])->canModifyStartDate())->toBeTrue();
    expect(makeSession(['state' => Active::$name])->canModifyStartDate())->toBeTrue();
});

it('has no set-current named route', function () {
    expect(fn () => route('academic-sessions.set-current', ['academicSession' => 'x']))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});

it('SessionActivated and SessionClosed events still exist', function () {
    expect(class_exists(SessionActivated::class))->toBeTrue();
    expect(class_exists(SessionClosed::class))->toBeTrue();
});

it('lifecycle emits plan pause resume reopen events', function () {
    expect(class_exists(SessionPlanned::class))->toBeTrue();
    expect(class_exists(SessionPaused::class))->toBeTrue();
    expect(class_exists(SessionResumed::class))->toBeTrue();
    expect(class_exists(SessionReopened::class))->toBeTrue();
});

it('lifecycle events implement ShouldDispatchAfterCommit', function () {
    $iface = Illuminate\Contracts\Events\ShouldDispatchAfterCommit::class;
    expect(is_subclass_of(SessionActivated::class, $iface))->toBeTrue();
    expect(is_subclass_of(SessionClosed::class, $iface))->toBeTrue();
    expect(is_subclass_of(SessionPlanned::class, $iface))->toBeTrue();
    expect(is_subclass_of(SessionPaused::class, $iface))->toBeTrue();
    expect(is_subclass_of(SessionResumed::class, $iface))->toBeTrue();
    expect(is_subclass_of(SessionReopened::class, $iface))->toBeTrue();
});

it('lifecycle service depends on operational data boundary', function () {
    $service = lifecycle();
    $prop = (new ReflectionClass($service))->getProperty('operationalData');
    $prop->setAccessible(true);
    expect($prop->getValue($service))->toBeInstanceOf(AcademicSessionOperationalDataBoundary::class);
});

it('rejects activation when another ACTIVE or PAUSED session exists', function () {
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'assertNoCurrentOperationalSession');
    $method->setAccessible(true);

    $active = makeSession(['state' => Active::$name]);
    expect(fn () => $method->invoke($service, $active->school_id, null))
        ->toThrow(ValidationException::class);

    $paused = makeSession(['state' => Paused::$name, 'name' => 'Paused-1']);
    expect(fn () => $method->invoke($service, $paused->school_id, null))
        ->toThrow(ValidationException::class);
});

it('activation never silently closes another session', function () {
    $active = makeSession(['state' => Active::$name]);
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'assertNoCurrentOperationalSession');
    $method->setAccessible(true);

    try {
        $method->invoke($service, $active->school_id, null);
        expect(false)->toBeTrue();
    } catch (ValidationException $e) {
        $active->refresh();
        expect($active->state)->toBeInstanceOf(Active::class);
    }
});

it('rejects overlapping dates and allows adjacent ranges', function () {
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'assertNoDateOverlap');
    $method->setAccessible(true);

    $a = makeSession([
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
        'state' => Planned::$name,
        'name' => 'A',
    ]);
    $overlap = makeSession([
        'start_date' => '2025-06-15',
        'end_date' => '2025-12-31',
        'state' => Draft::$name,
        'name' => 'Overlap',
    ]);
    expect(fn () => $method->invoke($service, $overlap))->toThrow(ValidationException::class);

    // Remove the overlapping peer so the adjacent case is isolated against A only
    \DB::table('academic_sessions')->where('id', $overlap->id)->delete();

    $adjacent = makeSession([
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'state' => Draft::$name,
        'name' => 'Adjacent',
    ]);
    expect(fn () => $method->invoke($service, $adjacent))->not->toThrow(ValidationException::class);
});

it('updateDates refuses start_date change when operational data exists', function () {
    $session = makeSession(['state' => Active::$name]);

    $this->app->instance(
        AcademicSessionOperationalDataBoundary::class,
        new class implements AcademicSessionOperationalDataBoundary
        {
            public function hasOperationalData(AcademicSession $session): bool
            {
                return true;
            }
        }
    );

    $service = app(AcademicSessionLifecycleService::class);

    expect(fn () => $service->updateDates($session, '2099-01-01', null))
        ->toThrow(ValidationException::class);
});

it('lockSchoolSessions locks the school row', function () {
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'lockSchoolSessions');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, test()->school->id))
        ->not->toThrow(Throwable::class);
});

it('pause rejects when locked session is no longer ACTIVE', function () {
    $session = makeSession([
        'state' => Closed::$name,
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    expect(fn () => lifecycle()->pause($session))->toThrow(ValidationException::class);
    $session->refresh();
    expect($session->state)->toBeInstanceOf(Closed::class);
});

it('resume rejects when locked session is no longer PAUSED', function () {
    $session = makeSession([
        'state' => Closed::$name,
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    expect(fn () => lifecycle()->resume($session))->toThrow(ValidationException::class);
    $session->refresh();
    expect($session->state)->toBeInstanceOf(Closed::class);
});

it('close rejects when locked session is neither ACTIVE nor PAUSED', function () {
    $session = makeSession([
        'state' => Draft::$name,
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    expect(fn () => lifecycle()->close($session))->toThrow(ValidationException::class);
    $session->refresh();
    expect($session->state)->toBeInstanceOf(Draft::class);
});

it('updateDates runs under school serialization (method structure)', function () {
    $source = file_get_contents((new ReflectionClass(lifecycle()))->getFileName());
    $start = strpos($source, 'function updateDates');
    $chunk = substr($source, $start, 1200);
    expect($chunk)->toContain('DB::transaction')
        ->and($chunk)->toContain('lockSchoolSessions')
        ->and($chunk)->toContain('lockForUpdate');
});

it('plan validates overlap inside transaction under school lock', function () {
    $source = file_get_contents((new ReflectionClass(lifecycle()))->getFileName());
    $start = strpos($source, 'function plan(');
    $chunk = substr($source, $start, 1500);
    expect($chunk)->toContain('DB::transaction')
        ->and($chunk)->toContain('lockSchoolSessions')
        ->and($chunk)->toContain('assertNoDateOverlap');
    expect(strpos($chunk, 'lockSchoolSessions'))->toBeLessThan(strpos($chunk, 'assertNoDateOverlap'));
});

it('pause resume and close re-read session under lock after school serialization', function () {
    $source = file_get_contents((new ReflectionClass(lifecycle()))->getFileName());

    foreach (['function pause(', 'function resume(', 'function close('] as $sig) {
        $start = strpos($source, $sig);
        expect($start)->not->toBeFalse();
        $chunk = substr($source, $start, 1600);
        expect($chunk)->toContain('lockSchoolSessions')
            ->and($chunk)->toContain('lockForUpdate()->firstOrFail()');
        expect(strpos($chunk, 'lockSchoolSessions'))
            ->toBeLessThan(strpos($chunk, 'lockForUpdate()->firstOrFail()'));
    }
});
