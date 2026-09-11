<?php

use App\Models\Academic\AcademicSession;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

it('registers five academic session states', function () {
    $states = AcademicSession::getStatesFor('state');

    expect($states->all())->toContain(Draft::$name)
        ->and($states->all())->toContain(Planned::$name)
        ->and($states->all())->toContain(Active::$name)
        ->and($states->all())->toContain(Paused::$name)
        ->and($states->all())->toContain(Closed::$name);
});

it('defaults new sessions to draft via model API', function () {
    expect(AcademicSession::getDefaultStateFor('state'))->toBe(Draft::class);
});

it('allows legal session transitions including CLOSED to ACTIVE', function () {
    $session = new AcademicSession(['state' => Draft::$name]);
    $session->state = new Draft($session);

    expect(fn () => $session->state->transitionTo(Planned::class))->not->toThrow(TransitionNotFound::class);

    $session->state = new Planned($session);
    expect(fn () => $session->state->transitionTo(Active::class))->not->toThrow(TransitionNotFound::class);

    $session->state = new Active($session);
    expect(fn () => $session->state->transitionTo(Paused::class))->not->toThrow(TransitionNotFound::class);

    $session->state = new Paused($session);
    expect(fn () => $session->state->transitionTo(Active::class))->not->toThrow(TransitionNotFound::class);

    $session->state = new Active($session);
    expect(fn () => $session->state->transitionTo(Closed::class))->not->toThrow(TransitionNotFound::class);

    $session->state = new Closed($session);
    expect(fn () => $session->state->transitionTo(Active::class))->not->toThrow(TransitionNotFound::class);
});

it('rejects illegal session transitions', function () {
    $session = new AcademicSession(['state' => Draft::$name]);
    $session->state = new Draft($session);

    expect(fn () => $session->state->transitionTo(Closed::class))->toThrow(TransitionNotFound::class);
    expect(fn () => $session->state->transitionTo(Paused::class))->toThrow(TransitionNotFound::class);

    $session->state = new Planned($session);
    expect(fn () => $session->state->transitionTo(Draft::class))->toThrow(TransitionNotFound::class);
});

it('scope active uses authoritative state name', function () {
    expect(AcademicSession::query()->active()->toSql())->toContain('state');
});
