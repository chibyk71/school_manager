<?php

namespace App\States\Promotion;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Promotion batch lifecycle states.
 *
 * Stored as short names in promotion_batches.status (draft, pending, …).
 * Transitions are enforced by the package; domain side-effects stay in PromotionService.
 */
abstract class PromotionBatchStatus extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class)
            ->allowTransition(Pending::class, Reviewing::class)
            ->allowTransition(Pending::class, Approved::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Reviewing::class, Pending::class)
            ->allowTransition(Reviewing::class, Approved::class)
            ->allowTransition(Reviewing::class, Cancelled::class)
            ->allowTransition(Approved::class, Executing::class)
            ->allowTransition(Executing::class, Completed::class);
    }

    public function isTerminal(): bool
    {
        return $this instanceof Completed || $this instanceof Cancelled;
    }

    public function isEditable(): bool
    {
        return $this instanceof Draft
            || $this instanceof Pending
            || $this instanceof Reviewing;
    }

    public function canBeApproved(): bool
    {
        return $this instanceof Pending || $this instanceof Reviewing;
    }

    public function canBeExecuted(): bool
    {
        return $this instanceof Approved;
    }

    public function canBeCancelled(): bool
    {
        return $this instanceof Pending || $this instanceof Reviewing;
    }

    public function canOverrideStudents(): bool
    {
        return $this instanceof Pending || $this instanceof Reviewing;
    }
}
