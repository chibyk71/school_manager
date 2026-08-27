<?php

namespace App\Jobs\Promotion;

use App\Models\Promotion\PromotionBatch;
use App\Services\PromotionService;
use App\States\Promotion\Draft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PopulatePromotionBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public PromotionBatch $batch)
    {
        $this->onQueue('promotions');
    }

    public function handle(PromotionService $service): void
    {
        $this->batch->refresh();

        if (! $this->batch->status->equals(Draft::class)) {
            Log::info('PopulatePromotionBatch: skipped non-draft batch', [
                'batch_id' => $this->batch->id,
                'status' => (string) $this->batch->status,
            ]);

            return;
        }

        try {
            $service->populateBatch($this->batch);
        } catch (\Throwable $e) {
            Log::error('PopulatePromotionBatch failed', [
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
