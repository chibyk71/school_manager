<?php

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Services\Academic\AcademicSessionLifecycleService;
use App\Services\Academic\NullAcademicSessionOperationalData;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->app->bind(
        AcademicSessionOperationalDataBoundary::class,
        NullAcademicSessionOperationalData::class
    );
    $this->school = School::factory()->create();
});

function lifecycle(): AcademicSessionLifecycleService
{
    return app(AcademicSessionLifecycleService::class);
}

function makeSession(array $attrs = []): AcademicSession
{
    $school = test()->school ?? School::factory()->create();

    return AcademicSession::factory()->create(array_merge([
        'school_id' => $school->id,
    ], $attrs));
}

it('operational data boundary returns false for all sessions in Phase 2', function () {
    $session = makeSession();
    expect(app(AcademicSessionOperationalDataBoundary::class)->hasOperationalData($session))->toBeFalse();
});

it('allows DRAFT sessions with incomplete dates', function () {
    $session = makeSession(['start_date' => null, 'end_date' => null, 'state' => Draft::$name]);
    expect($session->state)->toBeInstanceOf(Draft::class)->and($session->start_date)->toBeNull();
});

it('rejects activation when another ACTIVE or PAUSED session exists', function () {
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'assertNoCurrentOperationalSession');
    $method->setAccessible(true);

    $active = makeSession(['state' => Active::$name]);
    expect(fn () => $method->invoke($service, $active->school_id, null))->toThrow(ValidationException::class);

    $paused = makeSession(['state' => Paused::$name]);
    expect(fn () => $method->invoke($service, $paused->school_id, null))->toThrow(ValidationException::class);
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

it('rejects overlapping dates and allows adjacent ranges', function () {
    $service = lifecycle();
    $method = new ReflectionMethod($service, 'assertNoDateOverlap');
    $method->setAccessible(true);

    $a = makeSession(['start_date' => '2025-01-01', 'end_date' => '2025-06-30', 'state' => Planned::$name]);
    $overlap = makeSession(['school_id' => $a->school_id, 'start_date' => '2025-06-15', 'end_date' => '2025-12-31', 'state' => Draft::$name]);
    expect(fn () => $method->invoke($service, $overlap))->toThrow(ValidationException::class);

    $adjacent = makeSession(['school_id' => $a->school_id, 'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'state' => Draft::$name]);
    expect(fn () => $method->invoke($service, $adjacent))->not->toThrow(ValidationException::class);
});

it('has no set-current named route', function () {
    expect(fn () => route('academic-sessions.set-current', ['academicSession' => 'x']))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});

it('SessionActivated and SessionClosed events still exist', function () {
    expect(class_exists(SessionActivated::class))->toBeTrue();
    expect(class_exists(SessionClosed::class))->toBeTrue();
});

it('lifecycle service depends on operational data boundary', function () {
    $service = lifecycle();
    $prop = (new ReflectionClass($service))->getProperty('operationalData');
    $prop->setAccessible(true);
    expect($prop->getValue($service))->toBeInstanceOf(AcademicSessionOperationalDataBoundary::class);
});
