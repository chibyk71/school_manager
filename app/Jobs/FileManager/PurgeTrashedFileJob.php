<?php

namespace App\Jobs\FileManager;

use App\Models\FileManager\File;
use App\Services\FileManager\FileLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Durable purge of a single trashed File Manager-managed file.
 */
class PurgeTrashedFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly string $fileId) {}

    public function handle(FileLifecycleService $lifecycle): void
    {
        $file = File::query()->find($this->fileId);
        if ($file === null) {
            return;
        }

        $lifecycle->purge($file);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('file_manager.purge_job_failed', [
            'file_id' => $this->fileId,
            'error_class' => $e !== null ? $e::class : null,
            'message' => $e !== null ? $e->getMessage() : null,
        ]);

        // Terminal path: stop automatic rediscovery; preserve storage identity.
        try {
            app(FileLifecycleService::class)->markPurgeFailed(
                $this->fileId,
                $e !== null ? $e->getMessage() : 'retry_exhausted'
            );
        } catch (Throwable $inner) {
            Log::error('file_manager.purge_mark_failed_error', [
                'file_id' => $this->fileId,
                'error_class' => $inner::class,
            ]);
        }
    }
}
