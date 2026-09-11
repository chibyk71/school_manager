<?php

use App\Models\Academic\Term;
use App\States\Academic\Term\Active;
use App\States\Academic\Term\Closed;
use App\States\Academic\Term\Planned;
use App\States\Academic\TermState;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

it('defines three term states and no paused', function () {
    expect(Planned::$name)->toBe('planned')
        ->and(Active::$name)->toBe('active')
        ->and(Closed::$name)->toBe('closed');

    expect(class_exists(\App\States\Academic\Term\Paused::class))->toBeFalse();
});

it('defaults terms to planned', function () {
    expect(TermState::config()->defaultStateClass())->toBe(Planned::class);
});

it('allows legal term transitions', function () {
    $term = new Term(['state' => Planned::$name]);
    $term->state = new Planned($term);
    expect(fn () => $term->state->transitionTo(Active::class))->not->toThrow(TransitionNotFound::class);

    $term->state = new Active($term);
    expect(fn () => $term->state->transitionTo(Closed::class))->not->toThrow(TransitionNotFound::class);
});

it('rejects illegal term transitions including closed to active', function () {
    $term = new Term(['state' => Planned::$name]);
    $term->state = new Planned($term);
    expect(fn () => $term->state->transitionTo(Closed::class))->toThrow(TransitionNotFound::class);

    $term->state = new Closed($term);
    expect(fn () => $term->state->transitionTo(Active::class))->toThrow(TransitionNotFound::class);
    expect(fn () => $term->state->transitionTo(Planned::class))->toThrow(TransitionNotFound::class);
});

it('scope active uses state', function () {
    expect(Term::query()->active()->toSql())->toContain('state');
});
