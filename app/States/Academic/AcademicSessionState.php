<?php

namespace App\States\Academic;

use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Academic Session lifecycle states (authoritative).
 *
 * Concrete classes live under AcademicSession/ (not same directory as this base).
 * Spatie only auto-discovers siblings of the abstract class, so they are registered explicitly.
 */
abstract class AcademicSessionState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->registerState([
                Draft::class,
                Planned::class,
                Active::class,
                Paused::class,
                Closed::class,
            ])
            ->allowTransition(Draft::class, Planned::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Planned::class, Active::class)
            ->allowTransition(Active::class, Paused::class)
            ->allowTransition(Paused::class, Active::class)
            ->allowTransition(Active::class, Closed::class)
            ->allowTransition(Paused::class, Closed::class)
            ->allowTransition(Closed::class, Active::class);
    }

    public function isActive(): bool
    {
        return $this instanceof Active;
    }

    public function isClosed(): bool
    {
        return $this instanceof Closed;
    }

    public function isDraft(): bool
    {
        return $this instanceof Draft;
    }

    public function isPlanned(): bool
    {
        return $this instanceof Planned;
    }

    public function isPaused(): bool
    {
        return $this instanceof Paused;
    }
}
