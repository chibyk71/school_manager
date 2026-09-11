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
 * Stored as short names in academic_sessions.state (draft, planned, active, paused, closed).
 * Transitions are enforced by Spatie Model States; domain workflows live in later phases.
 */
abstract class AcademicSessionState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            // Structural transitions (Phase 1)
            ->allowTransition(Draft::class, Planned::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Planned::class, Active::class)
            ->allowTransition(Active::class, Paused::class)
            ->allowTransition(Paused::class, Active::class)
            ->allowTransition(Active::class, Closed::class)
            ->allowTransition(Paused::class, Closed::class)
            // Controlled reopening is part of the eventual domain model (structural only in Phase 1)
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
