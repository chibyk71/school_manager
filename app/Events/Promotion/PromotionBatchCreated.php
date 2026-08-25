<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PromotionBatchCreated Event
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Fired when a new PromotionBatch is successfully created (both manual and automatic via session close).
 *
 * Purpose:
 * - Notify relevant users (via listeners)
 * - Trigger PopulatePromotionBatch job (if not already populated)
 * - Log the creation for audit trail
 *
 * Broadcasts on the default channel if needed (can be used for real-time updates later).
 */

class PromotionBatchCreated
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
