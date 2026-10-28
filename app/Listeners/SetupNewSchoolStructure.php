<?php

namespace App\Listeners;

use App\Events\SchoolCreated;
use App\Models\User;
use App\Notifications\SchoolWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SetupNewSchoolStructure Listener (Phase 1)
 *
 * Queued welcome notification only. Operational onboarding (sections, class levels,
 * session) is deferred to Activate via SchoolLifecycleService::ensureOperationalOnboarding.
 *
 * SchoolCreated is dispatched afterCommit, so a queued re-fetch of School is safe.
 * Failures here must never roll back or misreport a successfully created Draft school.
 */
class SetupNewSchoolStructure implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SchoolCreated $event): void
    {
        try {
            if ($event->creatorId) {
                $admin = User::query()->find($event->creatorId);
                $admin?->notify(new SchoolWelcomeNotification($event->school));
            }

            Log::info('SetupNewSchoolStructure: welcome only; operational onboarding deferred to Activate', [
                'school_id' => $event->school->id,
                'school_name' => $event->school->name,
                'creator_id' => $event->creatorId,
            ]);
        } catch (\Throwable $e) {
            Log::error('SetupNewSchoolStructure failed after school create', [
                'school_id' => $event->school->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
