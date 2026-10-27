<?php

namespace App\Listeners;

use App\Events\SchoolCreated;
use App\Models\User;
use App\Notifications\SchoolWelcomeNotification;
use Illuminate\Support\Facades\Log;

/**
 * SetupNewSchoolStructure Listener (Phase 1)
 *
 * Runs inline on SchoolCreated (not queued).
 *
 * Why not ShouldQueue:
 * SchoolCreated uses SerializesModels. A queued listener re-fetches the School via
 * firstOrFail(); under RefreshDatabase / open transactions that restore can throw
 * ModelNotFoundException and surface as HTTP 404 on POST /schools.
 *
 * Phase 1 work is intentionally light: optional welcome notification only.
 * Operational onboarding (sections, class levels, session) is deferred to Activate
 * via SchoolLifecycleService::ensureOperationalOnboarding — not this listener.
 */
class SetupNewSchoolStructure
{
    public function handle(SchoolCreated $event): void
    {
        try {
            if ($event->creatorId) {
                $admin = User::query()->find($event->creatorId);
                // notifyNow avoids a second SerializesModels re-fetch of School on the queue.
                $admin?->notifyNow(new SchoolWelcomeNotification($event->school));
            }

            Log::info('SetupNewSchoolStructure: welcome only; operational onboarding deferred to Activate', [
                'school_id' => $event->school->id,
                'school_name' => $event->school->name,
                'creator_id' => $event->creatorId,
            ]);
        } catch (\Throwable $e) {
            // School was already created successfully — do not fail the HTTP create path.
            Log::error('SetupNewSchoolStructure failed after school create', [
                'school_id' => $event->school->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
