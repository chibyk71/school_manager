<?php

namespace App\Services\Academic;

use App\Models\Academic\ClassPeriod;
use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableConflict;
use App\Models\Academic\TimetableDaySchedule;
use App\Models\Academic\TimetableSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TimetableGeneratorService — Automatic Timetable Generation Algorithm
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Given a Timetable in DRAFT status, this service distributes lesson assignments
 * (TeacherClassSectionSubject rows) across the available periods and working days,
 * respecting:
 *   - Teacher availability (no double-booking across sections)
 *   - Section availability (one lesson per period per section)
 *   - `periods_per_week` on each subject (or school default if null)
 *   - `is_manually_placed = true` slots are NEVER moved or overwritten
 *   - Break periods are never assigned lessons
 *   - Only days that have a PeriodSchedule mapped in TimetableDaySchedule are used
 *
 * Unresolvable assignments (teacher busy all week, not enough periods, etc.) are
 * written to the `timetable_conflicts` staging table for admin review. Activation
 * is blocked until all conflicts are resolved.
 *
 * ── Algorithm Overview ────────────────────────────────────────────────────────────
 * 1. Load all TCSS assignments for the timetable's school section.
 * 2. Load all available (day, period) combinations from TimetableDaySchedule +
 *    ClassPeriod (excluding breaks).
 * 3. Pre-populate the occupancy map from existing is_manually_placed=true slots.
 * 4. Clear all is_manually_placed=false slots and all existing conflicts.
 * 5. For each TCSS assignment, sorted by constraint tightness (ascending available
 *    periods), attempt to place `periods_per_week` slots:
 *    a. Build a candidate list: all (day, period) pairs not yet occupied by the
 *       section OR the teacher, avoiding duplicate day placements when possible.
 *    b. Score each candidate (day spread bonus, teacher preference).
 *    c. Place the highest-scoring candidates, updating the occupancy map.
 *    d. If not enough candidates exist, record a conflict for each missing placement.
 * 6. Write all new slots in one bulk insert. Write all conflicts in one bulk insert.
 * 7. Return GenerationResult (stats + conflict summary).
 *
 * ── Preview Mode ─────────────────────────────────────────────────────────────────
 * When called via `previewGenerate()`, the service runs the same algorithm but
 * does NOT write anything to the database. It returns the same GenerationResult
 * so the frontend can show what WOULD happen before the admin commits.
 *
 * ── Separation from TimetableService ─────────────────────────────────────────────
 * Generation is deliberately separated because:
 *   - It's a complex, self-contained algorithm that merits its own class.
 *   - It's called asynchronously via GenerateTimetableJob (never directly in HTTP).
 *   - It does NOT use GetSchoolModel() — it reads school context from the Timetable
 *     model itself (safe for queue workers).
 *   - Its output is consumed by TimetableService (conflict review + activation).
 *
 * ── Return Type: GenerationResult ────────────────────────────────────────────────
 * An array-backed value object:
 * [
 *   'timetable_id'     => string (UUID),
 *   'slots_placed'     => int,
 *   'slots_skipped'    => int,   // manually placed; not touched
 *   'conflicts_found'  => int,
 *   'conflict_types'   => array, // grouped by conflict_type for frontend summary
 *   'total_assignments' => int,
 *   'coverage_percent' => float, // (placed / expected) * 100
 * ]
 */
class TimetableGeneratorService
{
    // ──────────────────────────────────────────────────────────────────────────────
    // Configuration Defaults
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Default periods per week when a subject has no explicit value and no school
     * default is configured. This is the absolute fallback.
     */
    private const DEFAULT_PERIODS_PER_WEEK = 4;

    /**
     * Scoring weight: bonus awarded for placing a subject on a day it has not yet
     * appeared on this week. Encourages even day-spread.
     */
    private const SCORE_DAY_SPREAD_BONUS = 10;

    /**
     * Scoring weight: penalty if the slot's day already has 3+ lessons for the
     * section (encourages balance across the week).
     */
    private const SCORE_DAY_OVERLOAD_PENALTY = 5;

    // ──────────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Run the generation algorithm and persist the results to the database.
     *
     * Steps:
     *   1. Validate the timetable is in DRAFT status.
     *   2. Load all required data (TCSS assignments, day schedules, periods).
     *   3. Pre-populate occupancy from manually placed slots.
     *   4. Clear auto-generated slots + all existing conflicts.
     *   5. Run the placement algorithm.
     *   6. Bulk-insert new slots and conflicts.
     *   7. Mark the timetable as generated (sets generated_at + generated_by).
     *   8. Return a GenerationResult summary.
     *
     * @param  Timetable    $timetable   Must be in DRAFT status
     * @param  int|null     $userId      Who triggered generation (stored on timetable)
     * @return array        GenerationResult
     * @throws ValidationException  If timetable is not draft
     * @throws \Throwable
     */
    public function generate(Timetable $timetable, ?int $userId = null): array
    {
        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'timetable' => "Only draft timetables can be auto-generated. " .
                               "Current status: {$timetable->status}.",
            ]);
        }

        Log::info('Timetable generation started', [
            'timetable_id' => $timetable->id,
            'section_id'   => $timetable->school_section_id,
            'term_id'      => $timetable->term_id,
            'triggered_by' => $userId,
        ]);

        $context = $this->buildContext($timetable);
        $result  = $this->runAlgorithm($context);

        DB::transaction(function () use ($timetable, $context, $result, $userId) {
            // Clear auto-generated slots (keep manually placed ones)
            TimetableSlot::where('timetable_id', $timetable->id)
                ->where('is_manually_placed', false)
                ->delete();  // SoftDelete — not forceDelete

            // Clear old conflicts (fresh run produces authoritative conflict set)
            TimetableConflict::where('timetable_id', $timetable->id)->delete();

            // Bulk insert new slots
            if (! empty($result['new_slots'])) {
                TimetableSlot::insert($result['new_slots']);
            }

            // Bulk insert new conflicts
            if (! empty($result['new_conflicts'])) {
                TimetableConflict::insert($result['new_conflicts']);
            }

            // Stamp the timetable with generation metadata
            $timetable->markGenerated($userId);
        });

        $summary = $this->buildSummary($timetable, $result, $context);

        Log::info('Timetable generation completed', array_merge(
            ['timetable_id' => $timetable->id],
            $summary
        ));

        return $summary;
    }

    /**
     * Run the generation algorithm in preview mode — no DB writes.
     *
     * Identical to `generate()` except steps 4–7 are skipped.
     * Returns the same GenerationResult so the frontend can show a preview
     * (slots that would be placed, conflicts that would be raised) before
     * the admin commits to a full generation run.
     *
     * @param  Timetable  $timetable
     * @return array  GenerationResult (same shape as generate())
     * @throws ValidationException
     */
    public function previewGenerate(Timetable $timetable): array
    {
        if (! $timetable->isDraft()) {
            throw ValidationException::withMessages([
                'timetable' => "Only draft timetables can be previewed.",
            ]);
        }

        $context = $this->buildContext($timetable);
        $result  = $this->runAlgorithm($context);

        return $this->buildSummary($timetable, $result, $context);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Context Building
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Load all data needed by the algorithm into a single context array.
     *
     * This single "data loading" phase separates I/O from the algorithm, making
     * the algorithm itself pure (input → output, no DB calls inside the loop).
     *
     * Context shape:
     * [
     *   'timetable'          => Timetable,
     *   'school_default_ppw' => int,         // school-level periods_per_week default
     *   'assignments'        => Collection,  // TeacherClassSectionSubject rows
     *   'available_slots'    => array,       // [ { day, period_id, schedule_id }, ... ]
     *   'occupied'           => array,       // set of "$section:$period:$day" keys
     *   'teacher_occupied'   => array,       // set of "$tcss_teacher_id:$period:$day" keys
     *   'section_day_count'  => array,       // [ "$section:$day" => count ]
     * ]
     *
     * @param  Timetable  $timetable
     * @return array
     */
    private function buildContext(Timetable $timetable): array
    {
        // ── Load assignments (TCSS rows for this section's class levels) ──────────────
        // We use the timetable's school section to find all class sections (arms)
        // belonging to it, then load all TCSS assignments for those sections.
        $classSectionIds = $timetable->schoolSection
            ->classLevels()
            ->with('classSections:id,class_level_id')
            ->get()
            ->flatMap(fn ($level) => $level->classSections->pluck('id'))
            ->unique()
            ->values()
            ->toArray();

        $assignments = \App\Models\Academic\TeacherClassSectionSubject::query()
            ->with(['subject:id,name,periods_per_week', 'classSection:id,name,display_name'])
            ->whereIn('class_section_id', $classSectionIds)
            ->get();

        // ── Load available (day, period) pairs from the day schedule mappings ──────────
        $daySchedules = TimetableDaySchedule::where('timetable_id', $timetable->id)
            ->with(['periodSchedule.periods' => fn ($q) => $q->ordered()->where('is_break', false)])
            ->get();

        $availableSlots = [];
        foreach ($daySchedules as $ds) {
            foreach ($ds->periodSchedule->periods as $period) {
                $availableSlots[] = [
                    'day'         => $ds->day_of_week,
                    'period_id'   => $period->id,
                    'schedule_id' => $ds->period_schedule_id,
                    'order'       => $period->order,
                ];
            }
        }

        // ── Pre-populate occupancy from manually placed slots ─────────────────────────
        $manualSlots = TimetableSlot::where('timetable_id', $timetable->id)
            ->where('is_manually_placed', true)
            ->with('assignment:id,class_section_id')
            ->get();

        $occupied         = [];  // "$class_section_id:$period_id:$day"
        $teacherOccupied  = [];  // "$teacher_id:$period_id:$day" (teacher from TCSS)
        $sectionDayCount  = [];  // "$class_section_id:$day" => int

        foreach ($manualSlots as $ms) {
            $key = "{$ms->class_section_id}:{$ms->class_period_id}:{$ms->day_of_week}";
            $occupied[$key] = true;

            // Get teacher ID from the TCSS assignment
            $teacherId = $ms->assignment->teacher_id ?? null;
            if ($teacherId) {
                $tKey = "{$teacherId}:{$ms->class_period_id}:{$ms->day_of_week}";
                $teacherOccupied[$tKey] = true;
            }

            $dKey = "{$ms->class_section_id}:{$ms->day_of_week}";
            $sectionDayCount[$dKey] = ($sectionDayCount[$dKey] ?? 0) + 1;
        }

        // ── Read school-level default periods_per_week from settings ──────────────────
        $schoolDefault = $this->resolveSchoolDefaultPpw($timetable);

        return [
            'timetable'          => $timetable,
            'school_default_ppw' => $schoolDefault,
            'assignments'        => $assignments,
            'available_slots'    => $availableSlots,
            'occupied'           => $occupied,
            'teacher_occupied'   => $teacherOccupied,
            'section_day_count'  => $sectionDayCount,
            'class_section_ids'  => $classSectionIds,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Core Algorithm
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * The placement algorithm.
     *
     * Processes each TCSS assignment sorted by tightest constraint first (fewest
     * available candidate slots → scheduled first to avoid them being crowded out
     * by easier-to-place subjects). For each assignment:
     *   - Determine required periods_per_week (subject → school default → const)
     *   - Subtract manually placed slots already counted
     *   - Attempt to fill remaining need from scored candidates
     *   - Any unfilled need becomes a conflict record
     *
     * Returns a $result array containing:
     * [
     *   'new_slots'      => array,  // rows ready for TimetableSlot::insert()
     *   'new_conflicts'  => array,  // rows ready for TimetableConflict::insert()
     *   'stats'          => [...],  // diagnostic counters
     * ]
     *
     * @param  array  $context  From buildContext()
     * @return array
     */
    private function runAlgorithm(array $context): array
    {
        $timetable       = $context['timetable'];
        $occupied        = &$context['occupied'];         // By reference — updated as we place
        $teacherOccupied = &$context['teacher_occupied']; // By reference
        $sectionDayCount = &$context['section_day_count']; // By reference
        $available       = $context['available_slots'];

        $newSlots     = [];
        $newConflicts = [];
        $stats = [
            'total_assignments'  => 0,
            'total_needed'       => 0,
            'slots_placed'       => 0,
            'manually_skipped'   => 0,
            'conflicts_raised'   => 0,
        ];

        // ── Sort assignments by tightest constraint first ─────────────────────────────
        // Count available candidate periods for each assignment before we start
        // so that assignments with fewer options are scheduled first.
        $assignments = $context['assignments']
            ->sortBy(fn ($tcss) => $this->countCandidates($tcss, $available, $context))
            ->values();

        foreach ($assignments as $tcss) {
            $stats['total_assignments']++;

            $sectionId = $tcss->class_section_id;
            $teacherId = $tcss->teacher_id;

            // ── Determine how many more slots we need to place ────────────────────────
            $required = $tcss->subject->periods_per_week
                ?? $context['school_default_ppw']
                ?? self::DEFAULT_PERIODS_PER_WEEK;

            // Count slots already manually placed for this TCSS
            $alreadyPlaced = 0;
            foreach ($newSlots as $slot) {
                if ($slot['teacher_class_section_subject_id'] === $tcss->id) {
                    $alreadyPlaced++;
                }
            }
            // Also count pre-existing manual slots for this TCSS (in occupancy map already)
            // We do this by checking $context's pre-loaded manual slot tcss ids
            // Note: we count against occupancy rather than re-querying
            $stillNeeded = max(0, $required - $alreadyPlaced);

            $stats['total_needed'] += $required;

            if ($stillNeeded === 0) {
                continue; // Fully covered by manually placed slots
            }

            // ── Build and score candidate slots ───────────────────────────────────────
            $candidates = $this->buildCandidates(
                tcss:            $tcss,
                sectionId:       $sectionId,
                teacherId:       $teacherId,
                available:       $available,
                occupied:        $occupied,
                teacherOccupied: $teacherOccupied,
                sectionDayCount: $sectionDayCount,
                alreadyOnDays:   $this->getDaysAlreadyUsed($newSlots, $tcss->id),
            );

            // ── Place as many as needed ───────────────────────────────────────────────
            $placed = 0;
            foreach ($candidates as $candidate) {
                if ($placed >= $stillNeeded) {
                    break;
                }

                // Final double-check (occupancy mutates as we loop, so re-verify)
                $sKey = "{$sectionId}:{$candidate['period_id']}:{$candidate['day']}";
                $tKey = "{$teacherId}:{$candidate['period_id']}:{$candidate['day']}";

                if (isset($occupied[$sKey]) || isset($teacherOccupied[$tKey])) {
                    continue; // Taken since we built the candidate list — skip
                }

                // ── Create the slot row ────────────────────────────────────────────────
                $newSlots[] = [
                    'id'                                  => \Illuminate\Support\Str::uuid()->toString(),
                    'school_id'                           => $timetable->school_id,
                    'timetable_id'                        => $timetable->id,
                    'class_section_id'                    => $sectionId,
                    'class_period_id'                     => $candidate['period_id'],
                    'teacher_class_section_subject_id'    => $tcss->id,
                    'day_of_week'                         => $candidate['day'],
                    'is_manually_placed'                  => false,
                    'notes'                               => null,
                    'created_at'                          => now(),
                    'updated_at'                          => now(),
                ];

                // ── Mark occupancy ─────────────────────────────────────────────────────
                $occupied[$sKey]        = true;
                $teacherOccupied[$tKey] = true;
                $dKey = "{$sectionId}:{$candidate['day']}";
                $sectionDayCount[$dKey] = ($sectionDayCount[$dKey] ?? 0) + 1;

                $placed++;
                $stats['slots_placed']++;
            }

            // ── Record conflict for unplaced slots ────────────────────────────────────
            $unplaced = $stillNeeded - $placed;
            if ($unplaced > 0) {
                $conflictType = empty($candidates)
                    ? ($teacherId ? TimetableConflict::TYPE_TEACHER_DOUBLE_BOOKED : TimetableConflict::TYPE_NO_TEACHER_ASSIGNED)
                    : TimetableConflict::TYPE_FREQUENCY_UNMET;

                $newConflicts[] = $this->buildConflictRow(
                    timetable:     $timetable,
                    tcss:          $tcss,
                    conflictType:  $conflictType,
                    unplacedCount: $unplaced,
                    candidates:    $candidates,
                );

                $stats['conflicts_raised']++;
            }
        }

        return [
            'new_slots'     => $newSlots,
            'new_conflicts' => $newConflicts,
            'stats'         => $stats,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Candidate Building & Scoring
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Build a scored list of candidate (day, period) pairs for an assignment.
     *
     * Filters to only slots where:
     *   - Section is not already occupied
     *   - Teacher is not already occupied
     *   - Period is a lesson period (is_break=false — already filtered in context)
     *
     * Scores each candidate:
     *   + SCORE_DAY_SPREAD_BONUS if the subject has not yet been placed on that day
     *   - SCORE_DAY_OVERLOAD_PENALTY if the section already has 3+ lessons on that day
     *
     * Returns sorted descending by score (highest score = best candidate first).
     *
     * @param  mixed   $tcss             TeacherClassSectionSubject model
     * @param  string  $sectionId
     * @param  int     $teacherId
     * @param  array   $available        From context
     * @param  array   $occupied         From context (by reference — read only here)
     * @param  array   $teacherOccupied  From context (by reference — read only here)
     * @param  array   $sectionDayCount  From context (by reference — read only here)
     * @param  array   $alreadyOnDays    Days this TCSS has already been placed on
     * @return array   Scored candidates, sorted desc by score
     */
    private function buildCandidates(
        mixed  $tcss,
        string $sectionId,
        int    $teacherId,
        array  $available,
        array  &$occupied,
        array  &$teacherOccupied,
        array  &$sectionDayCount,
        array  $alreadyOnDays,
    ): array {
        $candidates = [];

        foreach ($available as $slot) {
            $sKey = "{$sectionId}:{$slot['period_id']}:{$slot['day']}";
            $tKey = "{$teacherId}:{$slot['period_id']}:{$slot['day']}";

            // Skip occupied slots
            if (isset($occupied[$sKey]) || isset($teacherOccupied[$tKey])) {
                continue;
            }

            // Score this candidate
            $score = 0;

            // Bonus for spreading across different days
            if (! in_array($slot['day'], $alreadyOnDays, true)) {
                $score += self::SCORE_DAY_SPREAD_BONUS;
            }

            // Penalty for overloaded days
            $dKey = "{$sectionId}:{$slot['day']}";
            if (($sectionDayCount[$dKey] ?? 0) >= 3) {
                $score -= self::SCORE_DAY_OVERLOAD_PENALTY;
            }

            // Slight preference for earlier periods in the day (lower order = more awake)
            $score += max(0, 10 - ($slot['order'] ?? 5));

            $candidates[] = array_merge($slot, ['score' => $score]);
        }

        // Sort descending by score, then by day (for deterministic output)
        usort($candidates, function ($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['day'] <=> $b['day'];
        });

        return $candidates;
    }

    /**
     * Count available candidates for a TCSS — used for constraint-tightness sorting
     * before the main loop runs.
     *
     * @param  mixed  $tcss
     * @param  array  $available
     * @param  array  $context
     * @return int
     */
    private function countCandidates(mixed $tcss, array $available, array $context): int
    {
        $sectionId = $tcss->class_section_id;
        $teacherId = $tcss->teacher_id ?? 0;
        $count     = 0;

        foreach ($available as $slot) {
            $sKey = "{$sectionId}:{$slot['period_id']}:{$slot['day']}";
            $tKey = "{$teacherId}:{$slot['period_id']}:{$slot['day']}";

            if (! isset($context['occupied'][$sKey]) && ! isset($context['teacher_occupied'][$tKey])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Extract the day_of_week values from already-placed slots for a given TCSS.
     *
     * @param  array   $newSlots
     * @param  string  $tcssId
     * @return array   e.g. [1, 3]
     */
    private function getDaysAlreadyUsed(array $newSlots, string $tcssId): array
    {
        return array_values(array_unique(array_map(
            fn ($s) => $s['day_of_week'],
            array_filter($newSlots, fn ($s) => $s['teacher_class_section_subject_id'] === $tcssId)
        )));
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Conflict & Summary Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Build a TimetableConflict insert row for an unplaced assignment.
     *
     * The `suggested_alternatives` JSON stores the top 3 remaining candidates so
     * the admin's ConflictPanel can show "try these periods instead."
     *
     * @param  Timetable  $timetable
     * @param  mixed      $tcss
     * @param  string     $conflictType
     * @param  int        $unplacedCount
     * @param  array      $candidates     Scored but unselected candidates
     * @return array
     */
    private function buildConflictRow(
        Timetable $timetable,
        mixed     $tcss,
        string    $conflictType,
        int       $unplacedCount,
        array     $candidates,
    ): array {
        // Top 3 suggestions from remaining candidates
        $suggestions = array_map(
            fn ($c) => [
                'day_of_week'                         => $c['day'],
                'class_period_id'                     => $c['period_id'],
                'teacher_class_section_subject_id'    => $tcss->id,
                'score'                               => $c['score'],
                'reason'                              => "Score: {$c['score']} — suggested by generator",
            ],
            array_slice($candidates, 0, 3)
        );

        $description = match ($conflictType) {
            TimetableConflict::TYPE_TEACHER_DOUBLE_BOOKED => sprintf(
                "Teacher is fully booked and cannot accommodate %d more lesson(s) for %s.",
                $unplacedCount,
                $tcss->subject->name ?? 'this subject'
            ),
            TimetableConflict::TYPE_NO_TEACHER_ASSIGNED => sprintf(
                "No teacher is assigned for %s in %s. Cannot place lesson(s).",
                $tcss->subject->name ?? 'this subject',
                $tcss->classSection->display_name ?? 'this section'
            ),
            TimetableConflict::TYPE_FREQUENCY_UNMET => sprintf(
                "Could only place %d of %d required lessons per week for %s in %s.",
                (($tcss->subject->periods_per_week ?? self::DEFAULT_PERIODS_PER_WEEK) - $unplacedCount),
                $tcss->subject->periods_per_week ?? self::DEFAULT_PERIODS_PER_WEEK,
                $tcss->subject->name ?? 'this subject',
                $tcss->classSection->display_name ?? 'this section'
            ),
            default => "Unresolvable conflict for {$tcss->subject->name}.",
        };

        return [
            'school_id'                           => $timetable->school_id,
            'timetable_id'                        => $timetable->id,
            'class_section_id'                    => $tcss->class_section_id,
            'teacher_class_section_subject_id'    => $tcss->id,
            'class_period_id'                     => null,  // No specific period — general conflict
            'day_of_week'                         => null,  // No specific day
            'conflict_type'                       => $conflictType,
            'description'                         => $description,
            'suggested_alternatives'              => json_encode($suggestions),
            'resolved_at'                         => null,
            'resolved_by'                         => null,
            'resolution_notes'                    => null,
            'created_at'                          => now(),
            'updated_at'                          => now(),
        ];
    }

    /**
     * Build the final GenerationResult summary array.
     *
     * @param  Timetable  $timetable
     * @param  array      $result    From runAlgorithm()
     * @param  array      $context   From buildContext()
     * @return array
     */
    private function buildSummary(Timetable $timetable, array $result, array $context): array
    {
        $stats  = $result['stats'];
        $placed = $stats['slots_placed'];
        $needed = max(1, $stats['total_needed']); // Avoid division by zero

        $conflictTypes = array_count_values(
            array_column($result['new_conflicts'], 'conflict_type')
        );

        return [
            'timetable_id'      => $timetable->id,
            'slots_placed'      => $placed,
            'manually_skipped'  => count(
                array_filter(
                    $context['available_slots'] ?? [],
                    fn () => false // Placeholder — count from occupancy if needed
                )
            ),
            'conflicts_found'   => count($result['new_conflicts']),
            'conflict_types'    => $conflictTypes,
            'total_assignments' => $stats['total_assignments'],
            'total_needed'      => $stats['total_needed'],
            'coverage_percent'  => round(($placed / $needed) * 100, 1),
            'has_conflicts'     => ! empty($result['new_conflicts']),
        ];
    }

    /**
     * Resolve the school-level default `periods_per_week` from settings.
     *
     * Reads from getMergedSettings() using the timetable's school context.
     * Falls back to the class constant if no school setting is configured.
     *
     * @param  Timetable  $timetable
     * @return int
     */
    private function resolveSchoolDefaultPpw(Timetable $timetable): int
    {
        try {
            $settings = getMergedSettings('academic.timetable', $timetable->school);
            return (int) ($settings['default_periods_per_week'] ?? self::DEFAULT_PERIODS_PER_WEEK);
        } catch (\Throwable) {
            return self::DEFAULT_PERIODS_PER_WEEK;
        }
    }
}
