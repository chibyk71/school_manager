<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PromotionBatchCompleted Event
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a PromotionBatch has been fully executed and all students processed successfully.
 *
 * Purpose:
 * - Notify stakeholders that promotion is complete
 * - Trigger final notifications (student outcome, parent SMS, etc.)
 * - Allow generation of reports/transcripts
 * - Mark the batch as terminal
 */

class PromotionBatchCompleted
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
