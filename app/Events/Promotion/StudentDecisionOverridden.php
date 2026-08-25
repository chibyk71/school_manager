<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionStudent;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * StudentDecisionOverridden Event
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired whenever a human overrides the system-computed recommendation for a student
 * during the review phase (final_decision is set on PromotionStudent).
 *
 * Purpose:
 * - Audit trail for overrides (who changed what and why)
 * - Notify other reviewers or approvers of the change
 * - Allow logging of override statistics per batch
 * - Can trigger targeted notifications to parents/teachers if needed
 *
 * This event helps maintain transparency in the promotion process.
 */

class StudentDecisionOverridden
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PromotionStudent $promotionStudent;
    public User $overriddenBy;
    public string $oldRecommendation;
    public string $newDecision;
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(
        PromotionStudent $promotionStudent,
        User $overriddenBy,
        string $oldRecommendation,
        string $newDecision,
        ?string $reason = null
    ) {
        $this->promotionStudent = $promotionStudent;
        $this->overriddenBy = $overriddenBy;
        $this->oldRecommendation = $oldRecommendation;
        $this->newDecision = $newDecision;
        $this->reason = $reason;
    }
}
