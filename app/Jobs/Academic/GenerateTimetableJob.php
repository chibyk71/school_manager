<?php

namespace App\Jobs\Academic;

use App\Models\Academic\Timetable;
use App\Notifications\Academic\TimetableGeneratedNotification;
use App\Services\Academic\TimetableGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * GenerateTimetableJob — Queued Timetable Generation Dispatcher
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Dispatches the TimetableGeneratorService on a background queue so that the HTTP
 * request that triggered generation returns immediately (generation can take
 * several seconds for large sections). On completion, notifies all school admins
 * with a summary of placed slots and unresolved conflicts.
 *
 * ── Why a Dedicated Job ───────────────────────────────────────────────────────────
 * - Generation is CPU-bound and may take 2–10+ seconds for sections with many
 *   class arms, subjects, and teachers.
 * - Running it synchronously would block the HTTP worker and time out on large
 *   datasets. Offloading to the queue gives instant 202 feedback to the admin.
 * - The job is idempotent: if re-queued (retry after failure), it clears
 *   auto-generated slots first, so re-running is always safe.
 *
 * ── Key Design Decisions ──────────────────────────────────────────────────────────
 * 1. `$timetable` is model-serialized via `SerializesModels`. Laravel re-fetches
 *    the model from the DB when the job runs, ensuring we always have fresh state
 *    (avoids stale model data if the job sits in the queue for a while).
 *
 * 2. We NEVER call `GetSchoolModel()` here. This job runs in a queue worker context
 *    where no HTTP request is active, so the school manager binding is unavailable.
 *    All school context is read from `$this->timetable->school_id` directly.
 *
 * 3. `$preview` mode runs the same algorithm but skips DB writes, then notifies
 *    admins with the hypothetical result. Useful for a "dry run" from the UI.
 *
 * 4. Failed jobs are retried up to 3 times with exponential backoff. On exhaustion,
 *    the timetable status is left as DRAFT and admins are notified of the failure.
 *
 * ── Failure Handling ──────────────────────────────────────────────────────────────
 * - `failed()` method catches the final failure, logs it, and notifies admins so
 *   they are not left waiting indefinitely for a result that will never arrive.
 * - The timetable's `generated_at` remains null if generation never completed,
 *   giving the UI a clear "not yet generated" signal.
 *
 * ── Multi-Tenant Safety ───────────────────────────────────────────────────────────
 * The job reads `$timetable->school_id` for all school-scoped queries. It loads
 * admin users to notify via `$timetable->school->users()->whereHas(...)` so the
 * notification target list is always correct for the right tenant.
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────────
 *   // Standard generation (controller dispatches this)
 *   GenerateTimetableJob::dispatch($timetable, $userId);
 *
 *   // Preview run (no DB writes, just see what would happen)
 *   GenerateTimetableJob::dispatch($timetable, $userId, preview: true);
 *
 *   // Delayed dispatch (e.g. schedule for off-peak hours)
 *   GenerateTimetableJob::dispatch($timetable, $userId)->delay(now()->addMinutes(5));
 */
class GenerateTimetableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is considered failed.
     * Three attempts with exponential backoff (30s, 60s, 120s).
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds before the job is killed.
     * Generation for very large timetables can take ~30s; 120s gives ample headroom.
     *
     * @var int
     */
    public int $timeout = 120;

    /**
     * Backoff intervals (in seconds) between retry attempts.
     * Exponential: 30s → 60s → 120s.
     *
     * @var array<int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * The queue this job should run on.
     * Isolated from the default queue so heavy generation work doesn't block
     * lightweight jobs (emails, notifications, etc.).
     *
     * @var string
     */
    public string $queue = 'timetable';

    /**
     * @param  Timetable  $timetable  The timetable to generate slots for.
     * @param  int|null   $userId     The ID of the user who triggered generation.
     *                                Stored on the timetable via `markGenerated()`.
     * @param  bool       $preview    When true, run the algorithm but skip DB writes.
     */
    public function __construct(
        public readonly Timetable $timetable,
        public readonly ?int      $userId  = null,
        public readonly bool      $preview = false,
    ) {}

    /**
     * Execute the job.
     *
     * Delegates entirely to TimetableGeneratorService. The service handles:
     *   - Clearing old auto-generated slots
     *   - Running the placement algorithm
     *   - Writing new slots + conflicts to the DB
     *   - Updating `timetable.generated_at`
     *
     * After the service completes, this job loads the notifiable users and sends
     * the TimetableGeneratedNotification with the result summary.
     *
     * @param  TimetableGeneratorService  $generator  Injected by Laravel's IoC container
     */
    public function handle(TimetableGeneratorService $generator): void
    {
        Log::info('GenerateTimetableJob started', [
            'timetable_id' => $this->timetable->id,
            'preview'      => $this->preview,
            'attempt'      => $this->attempts(),
            'triggered_by' => $this->userId,
        ]);

        // Re-fetch the timetable to ensure we have the latest status.
        // If the timetable was deleted or activated while the job was queued,
        // we abort gracefully rather than corrupting state.
        $timetable = Timetable::find($this->timetable->id);

        if (! $timetable) {
            Log::warning('GenerateTimetableJob: timetable no longer exists, aborting.', [
                'timetable_id' => $this->timetable->id,
            ]);
            return;
        }

        if (! $timetable->isDraft()) {
            Log::warning('GenerateTimetableJob: timetable is no longer a draft, aborting.', [
                'timetable_id' => $timetable->id,
                'status'       => $timetable->status,
            ]);
            return;
        }

        // ── Run the generation (or preview) ───────────────────────────────────────
        if ($this->preview) {
            $result = $generator->previewGenerate($timetable);
        } else {
            $result = $generator->generate($timetable, $this->userId);
        }

        Log::info('GenerateTimetableJob completed', [
            'timetable_id'    => $timetable->id,
            'preview'         => $this->preview,
            'slots_placed'    => $result['slots_placed'],
            'conflicts_found' => $result['conflicts_found'],
            'coverage'        => $result['coverage_percent'] . '%',
        ]);

        // ── Notify admins ─────────────────────────────────────────────────────────
        $this->notifyAdmins($timetable, $result);
    }

    /**
     * Handle a job failure after all retry attempts are exhausted.
     *
     * Logs the exception at error level and notifies admins so they are not left
     * waiting indefinitely. The timetable status remains DRAFT so the admin can
     * retry manually from the UI.
     *
     * @param  \Throwable  $exception  The exception that caused the final failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateTimetableJob failed after all retries', [
            'timetable_id' => $this->timetable->id,
            'preview'      => $this->preview,
            'error'        => $exception->getMessage(),
            'trace'        => $exception->getTraceAsString(),
        ]);

        // Notify admins of the failure so they can investigate or retry.
        // We pass a null result to signal failure mode in the notification.
        $timetable = Timetable::find($this->timetable->id);
        if ($timetable) {
            $this->notifyAdmins($timetable, null, $exception);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Load admin users for the timetable's school and send the notification.
     *
     * Loads users with admin-level roles from the school's user roster.
     * Uses Notification::send() (fan-out) rather than a loop so Laravel can
     * batch the notifications efficiently.
     *
     * We read the school directly from the timetable model (not GetSchoolModel())
     * for queue-worker safety.
     *
     * @param  Timetable        $timetable
     * @param  array|null       $result     Null when called from failed()
     * @param  \Throwable|null  $exception  Present when called from failed()
     */
    private function notifyAdmins(
        Timetable   $timetable,
        ?array      $result    = null,
        ?\Throwable $exception = null,
    ): void {
        try {
            // Load school with its admin users in a single eager-loaded query.
            // We look for users with roles that have admin-level timetable access.
            // Adjust the role names to match your Laratrust setup.
            $admins = $timetable->school
                ->users()
                ->whereHas('roles', fn ($q) =>
                    $q->whereIn('name', ['admin', 'super-admin', 'principal', 'vice-principal'])
                )
                ->get();

            if ($admins->isEmpty()) {
                Log::warning('GenerateTimetableJob: no admins found to notify.', [
                    'timetable_id' => $timetable->id,
                    'school_id'    => $timetable->school_id,
                ]);
                return;
            }

            Notification::send(
                $admins,
                new TimetableGeneratedNotification(
                    timetable:   $timetable,
                    result:      $result,
                    preview:     $this->preview,
                    exception:   $exception,
                )
            );
        } catch (\Throwable $e) {
            // Never let a notification failure bring down the job — log and move on.
            Log::error('GenerateTimetableJob: failed to send admin notifications.', [
                'timetable_id' => $timetable->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
