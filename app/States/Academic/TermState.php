<?php

namespace App\States\Academic;

use App\States\Academic\Term\Active;
use App\States\Academic\Term\Closed;
use App\States\Academic\Term\Planned;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Term lifecycle states (authoritative).
 *
 * Stored as short names in terms.state (planned, active, closed).
 * There is no PAUSED Term state. Full activation/closing workflows belong to later phases.
 */
abstract class TermState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Planned::class)
            ->allowTransition(Planned::class, Active::class)
            ->allowTransition(Active::class, Closed::class);
    }

    public function isActive(): bool
    {
        return $this instanceof Active;
    }

    public function isClosed(): bool
    {
        return $this instanceof Closed;
    }

    public function isPlanned(): bool
    {
        return $this instanceof Planned;
    }
}
