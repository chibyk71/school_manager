<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PromotionBatchCancelled Event
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a PromotionBatch is cancelled by an authorized user (status changed to 'cancelled').
 *
 * Purpose:
 * - Notify relevant users (admins, initiators, reviewers) that the batch has been cancelled
 * - Record the cancellation reason (stored in batch metadata)
 * - Allow cleanup or archiving workflows
 * - Trigger any necessary reversal notifications
 *
 * This is a terminal event — no further transitions are possible after cancellation.
 */

class PromotionBatchCancelled
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
