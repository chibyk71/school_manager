<?php

namespace App\Notifications\Academic;

use App\Models\Academic\Timetable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TimetableGeneratedNotification — Admin Notification for Timetable Generation Results
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Notifies school administrators when an auto-generation run (or preview) completes.
 * Sent via the `database` channel (in-app bell icon) and optionally `mail`.
 *
 * Three distinct states are covered by this single notification class:
 *
 *   1. SUCCESS (result not null, no exception)
 *      - Generation completed. Shows slots placed, conflicts needing review,
 *        and a direct link to the timetable builder.
 *
 *   2. PREVIEW (result not null, preview=true, no exception)
 *      - Dry-run completed. Shows what WOULD happen without DB changes.
 *        Message is clearly labelled "Preview Result" to avoid confusion.
 *
 *   3. FAILURE (result null, exception present)
 *      - Generation failed after all retry attempts. Includes error summary so
 *        admins can act without needing to check server logs. Links to the
 *        timetable so they can retry or investigate.
 *
 * ── Channel Strategy ─────────────────────────────────────────────────────────────
 * `database`  — Always sent. Powers the in-app notification bell. Stored in the
 *               `notifications` table (Laravel's default polymorphic table).
 * `mail`      — Only sent for failures and for generation runs that produced
 *               conflicts, since those require admin action. Pure success + no
 *               conflicts sends database only (avoids inbox noise).
 *
 * ── Implements ShouldQueue ───────────────────────────────────────────────────────
 * The notification itself is queued via the `notifications` queue so that sending
 * emails does not block the timetable queue worker.
 *
 * ── Database Notification Payload Shape ──────────────────────────────────────────
 * Stored in `notifications.data` as JSON:
 * {
 *   "type":             "timetable_generated" | "timetable_preview" | "timetable_failed",
 *   "timetable_id":     "uuid",
 *   "timetable_title":  "2025/2026 JSS Timetable",
 *   "section_name":     "Junior Secondary School",
 *   "term_name":        "First Term",
 *   "slots_placed":     42,
 *   "conflicts_found":  3,
 *   "coverage_percent": 87.5,
 *   "preview":          false,
 *   "failed":           false,
 *   "error_message":    null,
 *   "url":              "/timetable/{uuid}",
 *   "generated_at":     "2025-01-15 10:30:00"
 * }
 *
 * ── Multi-Tenant Safety ───────────────────────────────────────────────────────────
 * All data is read from the passed `$timetable` model — no `GetSchoolModel()` calls.
 * Safe to use in queue-worker context.
 */
class TimetableGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The queue this notification's mail sending should run on.
     *
     * @var string
     */
    public string $queue = 'notifications';

    /**
     * @param  Timetable        $timetable  The timetable that was generated.
     * @param  array|null       $result     GenerationResult from TimetableGeneratorService.
     *                                      Null when called from GenerateTimetableJob::failed().
     * @param  bool             $preview    True if this was a preview (dry-run) run.
     * @param  \Throwable|null  $exception  Present when generation failed completely.
     */
    public function __construct(
        public readonly Timetable   $timetable,
        public readonly ?array      $result    = null,
        public readonly bool        $preview   = false,
        public readonly ?\Throwable $exception = null,
    ) {}

    /**
     * Determine which channels to use based on the result state.
     *
     * - Database: always (in-app notification bell).
     * - Mail: only for failures or for runs that produced conflicts requiring action.
     *
     * @param  mixed  $notifiable  The User model receiving the notification
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        $hasFailed    = $this->exception !== null || $this->result === null;
        $hasConflicts = ($this->result['conflicts_found'] ?? 0) > 0;

        if ($hasFailed || $hasConflicts) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Build the database notification payload.
     *
     * This is the data stored in `notifications.data` and read by the frontend
     * notification bell component. The `type` field is used by the frontend to
     * select the correct icon and colour for the notification card.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $hasFailed = $this->exception !== null || $this->result === null;

        // Resolve display names — relations may not be loaded on the serialized model
        $timetableWithRelations = $this->timetable->loadMissing([
            'schoolSection:id,name,display_name',
            'term:id,name',
        ]);

        $sectionName = $timetableWithRelations->schoolSection?->display_name
            ?? $timetableWithRelations->schoolSection?->name
            ?? 'Unknown Section';

        $termName = $timetableWithRelations->term?->name ?? 'Unknown Term';

        $type = match (true) {
            $hasFailed        => 'timetable_failed',
            $this->preview    => 'timetable_preview',
            default           => 'timetable_generated',
        };

        return [
            'type'             => $type,
            'timetable_id'     => $this->timetable->id,
            'timetable_title'  => $this->timetable->title,
            'section_name'     => $sectionName,
            'term_name'        => $termName,
            'slots_placed'     => $this->result['slots_placed']     ?? 0,
            'conflicts_found'  => $this->result['conflicts_found']  ?? 0,
            'coverage_percent' => $this->result['coverage_percent'] ?? 0,
            'preview'          => $this->preview,
            'failed'           => $hasFailed,
            'error_message'    => $hasFailed
                ? ($this->exception?->getMessage() ?? 'An unknown error occurred during generation.')
                : null,
            'url'              => route('timetable.show', $this->timetable->id),
            'generated_at'     => now()->toDateTimeString(),
        ];
    }

    /**
     * Build the email message.
     *
     * Only sent for failures and for runs with unresolved conflicts (see `via()`).
     * Keeps the email concise — the full details are in the in-app notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $hasFailed    = $this->exception !== null || $this->result === null;
        $hasConflicts = ($this->result['conflicts_found'] ?? 0) > 0;
        $builderUrl   = route('timetable.show', $this->timetable->id);
        $title        = $this->timetable->title;

        if ($hasFailed) {
            return $this->buildFailureEmail($notifiable, $title, $builderUrl);
        }

        if ($hasConflicts) {
            return $this->buildConflictEmail($notifiable, $title, $builderUrl);
        }

        // Pure success, no conflicts — should not reach here per via() logic,
        // but implement defensively.
        return $this->buildSuccessEmail($notifiable, $title, $builderUrl);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Email Builders
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Email for a completely failed generation run.
     */
    private function buildFailureEmail(mixed $notifiable, string $title, string $url): MailMessage
    {
        $errorMessage = $this->exception?->getMessage() ?? 'Unknown error.';

        return (new MailMessage)
            ->subject("⚠️ Timetable Generation Failed: {$title}")
            ->error()
            ->greeting("Hello {$notifiable->name},")
            ->line("The auto-generation run for **{$title}** failed after multiple attempts.")
            ->line("**Error:** {$errorMessage}")
            ->line(
                "The timetable has been left in DRAFT status. You can retry generation " .
                "from the timetable builder, or contact support if the issue persists."
            )
            ->action('Open Timetable Builder', $url)
            ->line("If this error continues, please check your teacher assignments, " .
                   "period schedules, and subject configuration for this section.");
    }

    /**
     * Email for a successful generation that produced conflicts needing admin review.
     */
    private function buildConflictEmail(mixed $notifiable, string $title, string $url): MailMessage
    {
        $placed    = $this->result['slots_placed']    ?? 0;
        $conflicts = $this->result['conflicts_found'] ?? 0;
        $coverage  = $this->result['coverage_percent'] ?? 0;

        $previewLabel = $this->preview ? ' (Preview)' : '';

        return (new MailMessage)
            ->subject("🔔 Timetable Generated{$previewLabel} — {$conflicts} Conflict(s) Need Review: {$title}")
            ->warning()
            ->greeting("Hello {$notifiable->name},")
            ->line(
                $this->preview
                    ? "A **preview run** for **{$title}** has completed."
                    : "**{$title}** has been auto-generated."
            )
            ->line("**{$placed}** lesson slots were placed ({$coverage}% coverage).")
            ->line(
                "**{$conflicts}** assignment(s) could not be fully scheduled and have been " .
                "flagged as conflicts. You must resolve these before activating the timetable."
            )
            ->action(
                $this->preview ? 'View Preview Results' : 'Review Conflicts',
                $url
            )
            ->line(
                "Common causes include: a teacher being fully booked across the week, " .
                "insufficient lesson periods for the required frequency, or no teacher " .
                "assigned to a subject."
            );
    }

    /**
     * Email for a fully successful generation run with no conflicts.
     * (Defensive — not normally sent per via() logic.)
     */
    private function buildSuccessEmail(mixed $notifiable, string $title, string $url): MailMessage
    {
        $placed   = $this->result['slots_placed']    ?? 0;
        $coverage = $this->result['coverage_percent'] ?? 0;

        return (new MailMessage)
            ->subject("✅ Timetable Generated: {$title}")
            ->success()
            ->greeting("Hello {$notifiable->name},")
            ->line("**{$title}** has been auto-generated successfully.")
            ->line("**{$placed}** lesson slots were placed ({$coverage}% coverage) with no conflicts.")
            ->action('View Timetable', $url);
    }
}
