<?php

namespace App\Listeners\Promotion;

use App\Events\Academic\SessionClosed;
use App\Services\PromotionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * When an academic session is closed, auto-create a promotion batch (draft → populate job).
 * Manual create remains available via promotions.create permission.
 */
class TriggerPromotionOnSessionClose implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'promotions';

    public int $tries = 3;

    public function __construct(protected PromotionService $promotionService)
    {
    }

    public function handle(SessionClosed $event): void
    {
        $session = $event->session;

        Log::info('TriggerPromotionOnSessionClose: session closed', [
            'session_id' => $session->id,
            'school_id' => $session->school_id,
        ]);

        try {
            $this->promotionService->createBatchForSession($session, null, null, 'Auto-created on session close');
        } catch (ValidationException $e) {
            Log::info('TriggerPromotionOnSessionClose: batch already exists or validation failed', [
                'session_id' => $session->id,
                'errors' => $e->errors(),
            ]);
        } catch (\Throwable $e) {
            Log::error('TriggerPromotionOnSessionClose failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
