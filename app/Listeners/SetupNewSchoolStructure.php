<?php

namespace App\Listeners;

use App\Events\SchoolCreated;
use App\Jobs\SetupSchoolOnboarding;
use App\Models\User;
use App\Notifications\SchoolWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SetupNewSchoolStructure Listener
 *
 * Purpose & Context:
 * ------------------
 * This queued listener is triggered immediately when a new School (tenant) is created
 * via the SchoolCreated event.
 *
 * Its sole responsibility is to kick off the asynchronous onboarding process by
 * dispatching the heavy-lifting SetupSchoolOnboarding job.
 *
 * Why a Separate Listener?
 * ------------------------
 * - Keeps the SchoolCreated event handler lightweight and focused
 * - Ensures the HTTP onboarding request returns instantly (fast UX)
 * - Allows easy addition of future post-creation tasks (welcome email, analytics, etc.)
 * - Fully queued: Both listener and job run in the background
 * - Failure isolation: If the job fails, it doesn't affect school creation
 *
 * Flow:
 * -----
 * SchoolController → SchoolService::createSchool()
 * → event(new SchoolCreated($school))
 * → This listener fires → dispatches SetupSchoolOnboarding job
 * → Job creates sections, class levels, academic session, etc.
 *
 * Queue Behavior:
 * ---------------
 * - Implements ShouldQueue → listener itself is queued
 * - Uses InteractsWithQueue trait for queue control
 * - Job is also queued → double async for maximum responsiveness
 *
 * Extensibility:
 * --------------
 * Add more jobs/notifications here in the future:
 *   - SendSchoolWelcomeNotification::dispatch($admin, $school)
 *   - TrackAnalyticsEvent::dispatch('school.created', $school)
 *   - ProvisionBillingTrial::dispatch($school)
 */
class SetupNewSchoolStructure implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // Listener is stateless — nothing to inject
    }

    /**
     * Handle the SchoolCreated event.
     *
     * Dispatches the queued job that sets up the structural foundation
     * of a Nigerian school (sections, class levels, current session).
     *
     * @param  SchoolCreated  $event
     * @return void
     */
    public function handle(SchoolCreated $event): void
    {
        try {

            // Assuming $event->creatorId is the admin who created/owns it
            if ($event->creatorId) {
                $admin = User::find($event->creatorId);
                $admin?->notify(new SchoolWelcomeNotification($event->school));
            }

            // Dispatch the heavy onboarding job
            // This runs asynchronously on the queue
            SetupSchoolOnboarding::dispatch($event->school);

            Log::info('SetupNewSchoolStructure listener triggered onboarding job', [
                'school_id' => $event->school->id,
                'school_name' => $event->school->name,
                'creator_id' => $event->creatorId,
            ]);
        } catch (\Throwable $e) {
            // Critical: if dispatching fails, log it — but don't throw
            // School was already created successfully
            Log::error('Failed to dispatch SetupSchoolOnboarding job', [
                'school_id' => $event->school->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Optional: notify devs via Slack/email in production
        }
    }
}
