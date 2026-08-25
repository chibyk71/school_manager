<?php

namespace App\Services\Academic;

use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableDaySchedule;
use App\Models\Academic\TimetableSlot;
use App\Models\Academic\TimetableConflict;
use App\Rules\Academic\NoSectionConflict;
use App\Rules\Academic\NoTeacherConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * TimetableService — Core Business Logic Layer for Timetable Management
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────────
 * This service owns ALL write operations for the Timetable module EXCEPT
 * auto-generation (which lives in TimetableGeneratorService). It handles:
 *
 *   createTimetable()        Create header + day schedule mappings in one transaction
 *   updateTimetable()        Update header fields; block editing active timetables
 *   activateTimetable()      Single-active enforcement; blocks on unresolved conflicts
 *   archiveTimetable()       Mark a timetable archived (read-only historical record)
 *   deleteTimetable()        Soft-delete draft timetables only
 *
 *   addSlot()                Manually place one lesson slot onto the grid
 *   updateSlot()             Move or modify a slot; re-validate conflicts
 *   removeSlot()             Soft-delete a single slot; always safe
 *   bulkRemoveSlots()        Clear multiple slots (used after re-generation)
 *
 *   resolveConflict()        Admin resolves a TimetableConflict entry (3 strategies)
 *   skipConflict()           Mark a conflict resolved-by-skip with a mandatory reason
 *
 * ── What This Service Does NOT Do ────────────────────────────────────────────────
 * • Does NOT run the generation algorithm → TimetableGeneratorService
 * • Does NOT handle HTTP request/response → TimetableController
 * • Does NOT dispatch the generation job → TimetableController::generate()
 * • Does NOT validate request input → FormRequests
 *
 * ── Authorization Boundary ───────────────────────────────────────────────────────
 * Permission checks (Gate::authorize / $this->authorize) happen in controllers.
 * This service receives already-authorized, validated data and enforces BUSINESS
 * RULES only (e.g., "cannot activate if conflicts exist").
 *
 * ── Transaction Strategy ─────────────────────────────────────────────────────────
 * Every mutating method is wrapped in DB::transaction(). Reasons:
 *  1. addSlot() creates a TimetableSlot AND potentially clears a TimetableConflict
 *  2. activateTimetable() must archive all other active timetables atomically
 *  3. resolveConflict() may create a TimetableSlot + mark the conflict resolved
 *
 * ── Return Values ────────────────────────────────────────────────────────────────
 * All methods return the primary affected model (Timetable, TimetableSlot, etc.)
 * fresh-loaded with the relations the controller needs for its Resource response.
 *
 * ── Business Rule Failures ───────────────────────────────────────────────────────
 * Domain violations throw ValidationException so they integrate automatically with
 * Laravel's 422 response and the frontend's existing error handling pattern.
 *
 * ── Multi-Tenant Safety ──────────────────────────────────────────────────────────
 * The BelongsToSchool scope on all models means this service never needs to pass
 * school_id explicitly to queries — scoped automatically. But it DOES include
 * school_id when creating records because BelongsToSchool::bootBelongsToSchool()
 * requires GetSchoolModel() to be set, which is safe in HTTP context.
 */
class TimetableService
{
    // ──────────────────────────────────────────────────────────────────────────────
    // Timetable Header Operations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Create a new draft timetable with its day-schedule mappings.
     *
     * Accepts validated data from StoreTimetableRequest which already contains a
     * `day_schedules` nested array:
     *   [ { day_of_week: 1, period_schedule_id: 'uuid' }, ... ]
     *
     * All header columns + day schedules are created in one atomic transaction.
     * The status is always forced to 'draft' regardless of what was submitted.
     *
     * @param  array  $data  Validated from StoreTimetableRequest
     * @return Timetable     Fresh model with daySchedules.periodSchedule loaded
     * @throws \Throwable
     */
    public function createTimetable(array $data): Timetable
    {
        return DB::transaction(function () use ($data): Timetable {
            $timetable = Timetable::create([
                'school_section_id' => $data['school_section_id'],
                'term_id'           => $data['term_id'],
                'title'             => $data['title'],
                'effective_from'    => $data['effective_from'],
                'effective_to'      => $data['effective_to'] ?? null,
                'status'            => Timetable::STATUS_DRAFT,    // Always draft on create
                'notes'             => $data['notes'] ?? null,
                'options'           => $data['options'] ?? [],
            ]);

            $this->syncDaySchedules($timetable, $data['day_schedules'] ?? []);

            Log::info('Timetable created', [
                'timetable_id' => $timetable->id,
                'section_id'   => $timetable->school_section_id,
                'term_id'      => $timetable->term_id,
            ]);

            return $timetable->load('daySchedules.periodSchedule');
        });
    }

    /**
     * Update a timetable's header and/or day-schedule mappings.
     *
     * Business rules:
     * - Active timetables cannot be edited (use archive first).
     * - Archived timetables cannot be edited.
     * - Only draft timetables are fully editable.
     *
     * When `day_schedules` is present in $data, the existing mappings are replaced
     * entirely (delete-then-insert). Existing slots are NOT removed — the admin is
     * responsible for re-generating if the schedule changes affect the grid.
     *
     * @param  Timetable  $timetable  Existing timetable to update
     * @param  array      $data       Validated update payload (all fields optional)
     * @return Timetable
     * @throws ValidationException
     * @throws \Throwable
     */
    public function updateTimetable(Timetable $timetable, array $data): Timetable
    {
        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'timetable' => "Only draft timetables can be edited. " .
                               "Please archive the current timetable before making changes.",
            ]);
        }

        return DB::transaction(function () use ($timetable, $data): Timetable {
            $timetable->update(array_filter([
                'title'          => $data['title'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to'   => $data['effective_to'] ?? null,   // May be intentionally null
                'notes'          => $data['notes'] ?? null,
                'options'        => $data['options'] ?? null,
            ], fn ($v) => $v !== null));

            // Handle explicit null for effective_to (clearing the end date)
            if (array_key_exists('effective_to', $data) && $data['effective_to'] === null) {
                $timetable->update(['effective_to' => null]);
            }

            if (isset($data['day_schedules'])) {
                $this->syncDaySchedules($timetable, $data['day_schedules']);
            }

            return $timetable->fresh(['daySchedules.periodSchedule']);
        });
    }

    /**
     * Activate a timetable, archiving all other active ones for the same section+term.
     *
     * Delegates to Timetable::activate() which wraps the multi-step process in its
     * own transaction. We call it here to keep the controller thin but allow the
     * model to own the invariant logic (single-active, conflict check, archive loop).
     *
     * @param  Timetable  $timetable
     * @return Timetable
     * @throws ValidationException   If unresolved conflicts exist
     * @throws \Throwable
     */
    public function activateTimetable(Timetable $timetable): Timetable
    {
        // Timetable::activate() already throws ValidationException on conflicts.
        // We call it directly — the service just proxies + returns the fresh model.
        $timetable->activate();

        Log::info('Timetable activated', [
            'timetable_id' => $timetable->id,
            'section_id'   => $timetable->school_section_id,
            'term_id'      => $timetable->term_id,
        ]);

        return $timetable->fresh(['daySchedules.periodSchedule', 'slots']);
    }

    /**
     * Archive a timetable.
     *
     * Active or draft timetables can be archived. Archived timetables are read-only
     * historical records; they cannot be re-activated without going through the draft
     * flow again.
     *
     * @param  Timetable  $timetable
     * @return Timetable
     * @throws ValidationException   If already archived
     * @throws \Throwable
     */
    public function archiveTimetable(Timetable $timetable): Timetable
    {
        if ($timetable->isArchived()) {
            throw ValidationException::withMessages([
                'timetable' => "This timetable is already archived.",
            ]);
        }

        $timetable->archive();

        Log::info('Timetable archived', ['timetable_id' => $timetable->id]);

        return $timetable->fresh();
    }

    /**
     * Soft-delete a timetable.
     *
     * Only draft timetables can be deleted. Active and archived timetables are
     * historical records that must be preserved. Slots and conflicts are cascade-
     * deleted at the DB level (timetable_id ON DELETE CASCADE on those tables).
     *
     * @param  Timetable  $timetable
     * @return void
     * @throws ValidationException
     */
    public function deleteTimetable(Timetable $timetable): void
    {
        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'timetable' => "Only draft timetables can be deleted. " .
                               "Archive the timetable to remove it from the active view.",
            ]);
        }

        $timetable->delete();

        Log::info('Timetable deleted', ['timetable_id' => $timetable->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Slot Operations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Manually place a new lesson slot onto the timetable grid.
     *
     * Business rules enforced:
     * - Timetable must be in DRAFT status.
     * - Period must not be a break (checked via ClassPeriod::isAssignable()).
     * - Period must belong to the schedule mapped to that day_of_week.
     * - Section must not have another lesson in the same period+day.
     * - Teacher must not have another lesson in the same period+day.
     *
     * Note: The FormRequest (StoreTimetableSlotRequest) enforces rules 3–5 via
     * NoSectionConflict and NoTeacherConflict rules plus a withValidator() check.
     * This method trusts that the incoming $data is already validated and focuses
     * on the creation + audit trail.
     *
     * @param  Timetable  $timetable  Parent timetable (already confirmed draft)
     * @param  array      $data       Validated from StoreTimetableSlotRequest
     * @return TimetableSlot          Fresh model with all relationships loaded
     * @throws ValidationException
     * @throws \Throwable
     */
    public function addSlot(Timetable $timetable, array $data): TimetableSlot
    {
        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'timetable' => "Slots can only be added to draft timetables.",
            ]);
        }

        return DB::transaction(function () use ($timetable, $data): TimetableSlot {
            $slot = TimetableSlot::create([
                'timetable_id'                       => $timetable->id,
                'class_section_id'                   => $data['class_section_id'],
                'class_period_id'                    => $data['class_period_id'],
                'teacher_class_section_subject_id'   => $data['teacher_class_section_subject_id'],
                'day_of_week'                        => $data['day_of_week'],
                'is_manually_placed'                 => $data['is_manually_placed'] ?? true,
                'notes'                              => $data['notes'] ?? null,
            ]);

            // If this slot resolves an existing unresolved conflict for the same
            // section+period+day, auto-resolve that conflict entry.
            $this->autoResolveConflictsForSlot($timetable, $slot);

            Log::info('Timetable slot added', [
                'slot_id'      => $slot->id,
                'timetable_id' => $timetable->id,
                'section_id'   => $slot->class_section_id,
                'day'          => $slot->day_of_week,
                'period_id'    => $slot->class_period_id,
            ]);

            return $slot->load([
                'classSection',
                'period',
                'assignment.subject',
                'assignment.teacher.profile',
            ]);
        });
    }

    /**
     * Move or modify an existing slot.
     *
     * This covers two UI interactions:
     *   1. Drag-drop: change day_of_week and/or class_period_id
     *   2. Edit dialog: change the TCSS assignment (swap teacher/subject)
     *
     * The slot is kept as is_manually_placed=true after any admin edit, regardless
     * of its original value, because the admin's intent is now locked in.
     *
     * Business rules:
     * - Timetable must be draft.
     * - New position must not conflict with another slot for the same section.
     * - New TCSS must not conflict with another slot for the same teacher.
     *
     * @param  TimetableSlot  $slot   Existing slot to modify
     * @param  array          $data   Validated patch payload (any subset of slot fields)
     * @return TimetableSlot
     * @throws ValidationException
     * @throws \Throwable
     */
    public function updateSlot(TimetableSlot $slot, array $data): TimetableSlot
    {
        $timetable = $slot->timetable;

        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'slot' => "Slots can only be modified on draft timetables.",
            ]);
        }

        // ── Run conflict checks on the proposed new values ────────────────────────────
        $proposedSection = $data['class_section_id']  ?? $slot->class_section_id;
        $proposedPeriod  = $data['class_period_id']   ?? $slot->class_period_id;
        $proposedDay     = $data['day_of_week']        ?? $slot->day_of_week;
        $proposedTcss    = $data['teacher_class_section_subject_id'] ?? $slot->teacher_class_section_subject_id;

        // Section conflict (exclude self)
        if (
            TimetableSlot::query()
                ->where('timetable_id',     $timetable->id)
                ->where('class_section_id', $proposedSection)
                ->where('class_period_id',  $proposedPeriod)
                ->where('day_of_week',      (int) $proposedDay)
                ->where('id', '!=',         $slot->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'class_section_id' => "This class section already has a lesson scheduled for that period and day.",
            ]);
        }

        // Teacher conflict (exclude self)
        if (
            TimetableSlot::teacherConflictExists(
                timetableId:   $timetable->id,
                tcssId:        $proposedTcss,
                periodId:      $proposedPeriod,
                dayOfWeek:     (int) $proposedDay,
                excludeSlotId: $slot->id,
            )
        ) {
            throw ValidationException::withMessages([
                'teacher_class_section_subject_id' => "This teacher already has a booking for that period and day.",
            ]);
        }

        return DB::transaction(function () use ($slot, $data): TimetableSlot {
            $slot->update([
                'class_section_id'                  => $data['class_section_id']                 ?? $slot->class_section_id,
                'class_period_id'                   => $data['class_period_id']                  ?? $slot->class_period_id,
                'teacher_class_section_subject_id'  => $data['teacher_class_section_subject_id'] ?? $slot->teacher_class_section_subject_id,
                'day_of_week'                       => $data['day_of_week']                       ?? $slot->day_of_week,
                'is_manually_placed'                => true,  // Admin touched it — protect it
                'notes'                             => $data['notes'] ?? $slot->notes,
            ]);

            return $slot->fresh([
                'classSection',
                'period',
                'assignment.subject',
                'assignment.teacher.profile',
            ]);
        });
    }

    /**
     * Soft-delete a single timetable slot.
     *
     * Safe to call on both manually placed and auto-generated slots.
     * Timetable must be draft.
     *
     * @param  TimetableSlot  $slot
     * @return void
     * @throws ValidationException
     */
    public function removeSlot(TimetableSlot $slot): void
    {
        if (! $slot->timetable->isDraft()) {
            throw ValidationException::withMessages([
                'slot' => "Slots can only be removed from draft timetables.",
            ]);
        }

        $slot->delete();

        Log::info('Timetable slot removed', [
            'slot_id'      => $slot->id,
            'timetable_id' => $slot->timetable_id,
        ]);
    }

    /**
     * Soft-delete multiple slots in one call (used after re-generation to clear
     * auto-generated slots before writing new ones).
     *
     * Calls delete() on each model individually (not mass-delete) to ensure model
     * events fire (e.g. activity log) and SoftDeletes sets deleted_at correctly.
     *
     * @param  Timetable  $timetable
     * @param  bool       $autoGeneratedOnly  If true, preserve is_manually_placed=true slots
     * @return int  Number of slots removed
     * @throws \Throwable
     */
    public function bulkRemoveSlots(Timetable $timetable, bool $autoGeneratedOnly = true): int
    {
        $query = TimetableSlot::where('timetable_id', $timetable->id);

        if ($autoGeneratedOnly) {
            $query->where('is_manually_placed', false);
        }

        $slots   = $query->get();
        $removed = 0;

        DB::transaction(function () use ($slots, &$removed) {
            foreach ($slots as $slot) {
                $slot->delete();
                $removed++;
            }
        });

        Log::info('Bulk slots removed', [
            'timetable_id'       => $timetable->id,
            'removed_count'      => $removed,
            'auto_generated_only' => $autoGeneratedOnly,
        ]);

        return $removed;
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Conflict Resolution
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a TimetableConflict using one of three strategies.
     *
     * Strategy: use_suggestion
     *   The admin accepts a generated suggestion. The suggestion is re-validated
     *   for freshness (race condition check) before applying. If the suggestion is
     *   still valid, a slot is created and the conflict is marked resolved.
     *
     * Strategy: manual
     *   The admin has manually placed a slot (via addSlot). This strategy just
     *   marks the conflict resolved with the admin's resolution_notes. The slot
     *   itself must already have been created separately.
     *
     * Strategy: skip
     *   The admin acknowledges the conflict but decides to leave the period empty
     *   (e.g., the subject has fewer required periods than planned). Requires a
     *   mandatory `resolution_notes` explaining why. The conflict is marked resolved
     *   with a "skipped" notation.
     *
     * @param  TimetableConflict  $conflict  The conflict to resolve
     * @param  array              $data      Validated from ResolveTimetableConflictRequest
     * @return TimetableConflict             Fresh model marked resolved
     * @throws ValidationException
     * @throws \Throwable
     */
    public function resolveConflict(TimetableConflict $conflict, array $data): TimetableConflict
    {
        if ($conflict->isResolved()) {
            throw ValidationException::withMessages([
                'conflict' => "This conflict has already been resolved.",
            ]);
        }

        if (! $conflict->timetable->isDraft()) {
            throw ValidationException::withMessages([
                'conflict' => "Conflicts can only be resolved on draft timetables.",
            ]);
        }

        return DB::transaction(function () use ($conflict, $data): TimetableConflict {
            $strategy = $data['strategy'];

            if ($strategy === 'use_suggestion') {
                $this->applyConflictSuggestion($conflict, $data);
            }

            // For 'manual' and 'skip' strategies, just mark resolved
            $conflict->markResolved(
                userId: auth()->id(),
                notes:  $data['resolution_notes'] ?? null,
            );

            Log::info('Timetable conflict resolved', [
                'conflict_id' => $conflict->id,
                'strategy'    => $strategy,
                'timetable_id' => $conflict->timetable_id,
            ]);

            return $conflict->fresh('resolvedBy');
        });
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Sync day-schedule mappings for a timetable.
     *
     * Replaces all existing TimetableDaySchedule rows for the timetable with the
     * new set. Called on create and on update (when day_schedules is provided).
     *
     * @param  Timetable  $timetable
     * @param  array      $daySchedules  [ { day_of_week, period_schedule_id }, ... ]
     */
    private function syncDaySchedules(Timetable $timetable, array $daySchedules): void
    {
        // Delete existing (no soft deletes on TimetableDaySchedule)
        TimetableDaySchedule::where('timetable_id', $timetable->id)->delete();

        // Re-insert fresh mappings
        $rows = array_map(fn (array $ds) => [
            'timetable_id'       => $timetable->id,
            'period_schedule_id' => $ds['period_schedule_id'],
            'day_of_week'        => $ds['day_of_week'],
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $daySchedules);

        if (! empty($rows)) {
            TimetableDaySchedule::insert($rows);
        }
    }

    /**
     * After manually placing a slot, look for unresolved conflicts in the
     * `timetable_conflicts` table that this slot could be considered to resolve,
     * and auto-mark them resolved.
     *
     * Specifically resolves TYPE_NO_AVAILABLE_PERIOD conflicts for the same
     * section+TCSS combination, since placing a slot is exactly the manual
     * resolution of "there was no period available."
     *
     * @param  Timetable      $timetable
     * @param  TimetableSlot  $slot
     */
    private function autoResolveConflictsForSlot(Timetable $timetable, TimetableSlot $slot): void
    {
        TimetableConflict::where('timetable_id', $timetable->id)
            ->where('class_section_id', $slot->class_section_id)
            ->where('teacher_class_section_subject_id', $slot->teacher_class_section_subject_id)
            ->whereNull('resolved_at')
            ->whereIn('conflict_type', [
                TimetableConflict::TYPE_NO_AVAILABLE_PERIOD,
                TimetableConflict::TYPE_FREQUENCY_UNMET,
            ])
            ->update([
                'resolved_at'      => now(),
                'resolved_by'      => auth()->id(),
                'resolution_notes' => 'Auto-resolved: slot manually placed by admin.',
            ]);
    }

    /**
     * Apply a generated suggestion to create a new TimetableSlot.
     *
     * Re-validates the suggestion for freshness (another admin may have already
     * placed a conflicting slot since the suggestion was generated). If still
     * valid, creates the slot as is_manually_placed=true (admin accepted it
     * intentionally).
     *
     * Suggestion shape (from suggested_alternatives JSON):
     *   { day_of_week, class_period_id, teacher_class_section_subject_id, score, reason }
     *
     * @param  TimetableConflict  $conflict
     * @param  array              $data       Validated request with suggestion_index
     * @throws ValidationException  If the suggestion is stale (no longer valid)
     */
    private function applyConflictSuggestion(TimetableConflict $conflict, array $data): void
    {
        $suggestion = $conflict->getSuggestion($data['suggestion_index'] ?? 0);

        if (! $suggestion) {
            throw ValidationException::withMessages([
                'suggestion_index' => "The selected suggestion does not exist for this conflict.",
            ]);
        }

        $timetableId = $conflict->timetable_id;
        $sectionId   = $conflict->class_section_id;

        // ── Re-validate for race conditions ───────────────────────────────────────
        // Section conflict check
        $sectionClash = TimetableSlot::query()
            ->where('timetable_id',     $timetableId)
            ->where('class_section_id', $sectionId)
            ->where('class_period_id',  $suggestion['class_period_id'])
            ->where('day_of_week',      $suggestion['day_of_week'])
            ->exists();

        if ($sectionClash) {
            throw ValidationException::withMessages([
                'suggestion_index' => "This suggestion is no longer available: the period was taken by another section.",
            ]);
        }

        // Teacher conflict check
        $tcssId = $suggestion['teacher_class_section_subject_id']
            ?? $conflict->teacher_class_section_subject_id;

        if (
            $tcssId &&
            TimetableSlot::teacherConflictExists(
                timetableId: $timetableId,
                tcssId:      $tcssId,
                periodId:    $suggestion['class_period_id'],
                dayOfWeek:   $suggestion['day_of_week'],
            )
        ) {
            throw ValidationException::withMessages([
                'suggestion_index' => "This suggestion is no longer available: the teacher is now booked elsewhere.",
            ]);
        }

        // ── Create the slot ───────────────────────────────────────────────────────
        TimetableSlot::create([
            'timetable_id'                     => $timetableId,
            'class_section_id'                 => $sectionId,
            'class_period_id'                  => $suggestion['class_period_id'],
            'teacher_class_section_subject_id' => $tcssId,
            'day_of_week'                      => $suggestion['day_of_week'],
            'is_manually_placed'               => true,
            'notes'                            => 'Created via conflict resolution suggestion.',
        ]);
    }
}
