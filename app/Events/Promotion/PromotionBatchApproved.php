<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PromotionBatchApproved Event
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a PromotionBatch is successfully approved (status changed to 'approved').
 *
 * Purpose:
 * - Notify approvers and executors
 * - Trigger any post-approval workflows
 * - Record in activity log (via Spatie LogsActivity on the model)
 *
 * This event is the signal that the batch is ready for execution.
 */

class PromotionBatchApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PromotionBatch $batch;

    /**
     * Create a new event instance.
     */
    public function __construct(PromotionBatch $batch)
    {
        $this->batch = $batch;
    }
}
