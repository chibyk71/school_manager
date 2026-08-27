<?php

namespace App\Events\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a promotion batch has been populated and enters pending review.
 */
class PromotionBatchReadyForReview
{
    use Dispatchable, SerializesModels;

    public function __construct(public PromotionBatch $batch)
    {
    }
}
