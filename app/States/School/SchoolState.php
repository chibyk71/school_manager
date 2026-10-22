<?php

namespace App\States\School;

use App\States\School\Active;
use App\States\School\Archived;
use App\States\School\Draft;
use App\States\School\Ready;
use App\States\School\Suspended;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * School lifecycle states (Phase 1 authoritative).
 *
 * Draft → Ready → Active ↔ Suspended → Archived
 * Archived may recover to Draft or Ready based on readiness.
 */
abstract class SchoolState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->registerState([
                Draft::class,
                Ready::class,
                Active::class,
                Suspended::class,
                Archived::class,
            ])
            ->allowTransition(Draft::class, Ready::class)
            ->allowTransition(Ready::class, Draft::class)
            ->allowTransition(Ready::class, Active::class)
            ->allowTransition(Active::class, Suspended::class)
            ->allowTransition(Suspended::class, Active::class)
            ->allowTransition(Active::class, Archived::class)
            ->allowTransition(Suspended::class, Archived::class)
            ->allowTransition(Archived::class, Draft::class)
            ->allowTransition(Archived::class, Ready::class);
    }

    public function isDraft(): bool
    {
        return $this instanceof Draft;
    }

    public function isReady(): bool
    {
        return $this instanceof Ready;
    }

    public function isActive(): bool
    {
        return $this instanceof Active;
    }

    public function isSuspended(): bool
    {
        return $this instanceof Suspended;
    }

    public function isArchived(): bool
    {
        return $this instanceof Archived;
    }

    /**
     * States that count toward schools.max_count capacity.
     */
    public function countsTowardCapacity(): bool
    {
        return $this instanceof Draft
            || $this instanceof Ready
            || $this instanceof Active
            || $this instanceof Suspended;
    }
}
