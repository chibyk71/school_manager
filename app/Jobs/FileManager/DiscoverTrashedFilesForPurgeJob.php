<?php

namespace App\Jobs\FileManager;

use App\Services\FileManager\FileLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled discovery of expired trash entries; dispatches per-file purge jobs.
 */
class DiscoverTrashedFilesForPurgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $limit = 50) {}

    public function handle(FileLifecycleService $lifecycle): void
    {
        $ids = $lifecycle->discoverEligibleForPurge($this->limit);
        foreach ($ids as $id) {
            PurgeTrashedFileJob::dispatch($id);
        }
    }
}
