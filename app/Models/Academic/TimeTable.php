<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Timetable — Master timetable document for a SchoolSection + Term.
 *
 * This is the root entity of the timetable module. It is the "notice board"
 * that holds all lesson assignments (TimetableSlots) for every class section
 * arm within a school section, for a specific academic term.
 *
 * ── What One Row Represents ───────────────────────────────────────────────────
 * "Senior Secondary — First Term 2025/2026 Timetable"
 *
 * This one Timetable record covers all SSS arms (SSS 1A, 1B, 2A, 2B, 3A, 3B).
 * Each arm's individual schedule is a filtered view of the slots table where
 * class_section_id = that arm's ID.
 *
 * ── Status Lifecycle ──────────────────────────────────────────────────────────
 *
 *   ┌──────────┐      activate()     ┌──────────┐    activate new    ┌──────────┐
 *   │  draft   │ ─────────────────→  │  active  │ ─────────────────→ │ archived │
 *   └──────────┘                     └──────────┘                    └──────────┘
 *        ↑                                                                 │
 *        └──────────────────────── (kept for history) ────────────────────┘
 *
 *   draft    → being built; not visible to students/teachers
 *   active   → currently in use; exactly one per (school_section, term) at a time
 *   archived → superseded; read-only; kept for history and reporting
 *
 * ── Single-Active Enforcement ─────────────────────────────────────────────────
 * DB-level partial unique indexes have poor cross-DB support in Laravel.
 * Instead, activate() uses a DB transaction to:
 *   1. Archive all other timetables for the same (school_section, term).
 *   2. Set this timetable to 'active'.
 * This is safe under Eloquent's implicit row-level locking within a transaction.
 *
 * ── Generator Integration ─────────────────────────────────────────────────────
 * The timetable is the entry point for auto-generation. Calling
 * GenerateTimetableJob::dispatch($timetable) triggers TimetableGeneratorService,
 * which reads the day-schedule mapping and teacher assignments to populate slots.
 * After generation, generated_at and generated_by are updated on this model.
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property string           $id                UUID primary key
 * @property string           $school_id
 * @property string           $school_section_id
 * @property int              $term_id
 * @property string           $title
 * @property \Carbon\Carbon   $effective_from
 * @property \Carbon\Carbon|null $effective_to
 * @property string           $status            draft | active | archived
 * @property string|null      $notes
 * @property \Carbon\Carbon|null $generated_at
 * @property int|null         $generated_by      FK → users
 * @property array|null       $options
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * ── Relationships ────────────────────────────────────────────────────────────
 * @property-read SchoolSection                                               $schoolSection
 * @property-read Term                                                        $term
 * @property-read \App\Models\User|null                                       $generatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableSlot>     $slots
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableDaySchedule> $daySchedules
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableConflict> $conflicts
 * @property-read \Illuminate\Database\Eloquent\Collection<TimetableConflict> $unresolvedConflicts
 */
class Timetable extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToSchool;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'timetables';

    protected $fillable = [
        'school_id',
        'school_section_id',
        'term_id',
        'title',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'generated_at',
        'generated_by',
        'options',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'generated_at' => 'datetime',
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Status Constants ──────────────────────────────────────────────────────

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';

    // ── HasTableQuery Configuration ───────────────────────────────────────────

    protected array $hiddenTableColumns = [
        'school_id',
        'options',
        'deleted_at',
    ];

    protected array $defaultHiddenColumns = [
        'notes',
        'generated_at',
        'generated_by',
        'effective_to',
        'created_at',
        'updated_at',
    ];

    protected array $globalFilterFields = [
        'title',
        'status',
    ];

    // ── Activity Logging ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('timetable')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Timetable \"{$this->title}\" was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The school section (e.g. "Senior Secondary") this timetable covers.
     */
    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    /**
     * The academic term this timetable is associated with.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * The user who last triggered auto-generation for this timetable.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }

    /**
     * All lesson slots in this timetable (every arm, every day, every period).
     * Filter by class_section_id to get one arm's view.
     */
    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class, 'timetable_id');
    }

    /**
     * Day → PeriodSchedule mappings for this timetable.
     * Ordered by day_of_week (1=Mon..7=Sun) for consistent grid rendering.
     */
    public function daySchedules(): HasMany
    {
        return $this->hasMany(TimetableDaySchedule::class, 'timetable_id')
            ->orderBy('day_of_week');
    }

    /**
     * All conflict records generated during auto-generation.
     * Includes both resolved and unresolved conflicts.
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(TimetableConflict::class, 'timetable_id');
    }

    /**
     * Only unresolved conflicts (resolved_at IS NULL).
     * Used by the admin conflict panel and the activation pre-check.
     */
    public function unresolvedConflicts(): HasMany
    {
        return $this->hasMany(TimetableConflict::class, 'timetable_id')
            ->whereNull('resolved_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filter to active timetables only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Filter to draft timetables only.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Filter to archived timetables only.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Filter to timetables for a specific term.
     *
     * Example: Timetable::forTerm($term->id)->active()->first()
     */
    public function scopeForTerm(Builder $query, int $termId): Builder
    {
        return $query->where('term_id', $termId);
    }

    /**
     * Filter to timetables for a specific school section.
     *
     * Example: Timetable::forSection($section->id)->forTerm($term->id)->get()
     */
    public function scopeForSection(Builder $query, string $sectionId): Builder
    {
        return $query->where('school_section_id', $sectionId);
    }

    // ── Option Accessor ───────────────────────────────────────────────────────

    /**
     * Retrieve a generator option with a fallback default.
     *
     * Example: $timetable->getOption('max_periods_per_teacher_per_day', 4)
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->options ?? [], $key, $default);
    }

    // ── State Helpers ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Whether the timetable has any unresolved conflicts.
     * Used by the builder UI to show the conflict warning banner,
     * and by activate() to prevent activation with pending conflicts.
     *
     * Issues one COUNT query — safe to call without loading the relationship.
     */
    public function hasConflicts(): bool
    {
        return $this->unresolvedConflicts()->exists();
    }

    /**
     * Count of unresolved conflicts.
     * Used for the conflict badge in the timetable list and builder header.
     */
    public function unresolvedConflictCount(): int
    {
        return $this->unresolvedConflicts()->count();
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Activate this timetable as the current one for its school section + term.
     *
     * Workflow (atomic transaction):
     *   1. Reject if this timetable has unresolved conflicts.
     *   2. Archive all other timetables for the same (school_section, term).
     *      If there is currently an active timetable, set its effective_to
     *      to (this timetable's effective_from - 1 day).
     *   3. Set this timetable to 'active'.
     *
     * @throws \RuntimeException if there are unresolved conflicts.
     * @throws \RuntimeException if the timetable is already active.
     */
    public function activate(): void
    {
        if ($this->isActive()) {
            throw new \RuntimeException(
                "Timetable \"{$this->title}\" is already active."
            );
        }

        if ($this->hasConflicts()) {
            $count = $this->unresolvedConflictCount();
            throw new \RuntimeException(
                "Cannot activate \"{$this->title}\" — it has {$count} unresolved conflict(s). " .
                "Please resolve all conflicts before activating."
            );
        }

        DB::transaction(function () {
            // Archive the currently active timetable for this section/term (if any)
            // and record when it ended (day before this one starts).
            $previouslyActive = self::where('school_section_id', $this->school_section_id)
                ->where('term_id', $this->term_id)
                ->where('status', self::STATUS_ACTIVE)
                ->whereKeyNot($this->id)
                ->first();

            if ($previouslyActive) {
                $previouslyActive->update([
                    'status' => self::STATUS_ARCHIVED,
                    'effective_to' => $this->effective_from->subDay(),
                ]);
            }

            // Archive all remaining draft/other timetables for same section/term.
            self::where('school_section_id', $this->school_section_id)
                ->where('term_id', $this->term_id)
                ->where('status', '!=', self::STATUS_ARCHIVED)
                ->whereKeyNot($this->id)
                ->update(['status' => self::STATUS_ARCHIVED]);

            // Activate this timetable.
            $this->update([
                'status' => self::STATUS_ACTIVE,
                'effective_to' => null, // active timetables have no end date
            ]);
        });
    }

    /**
     * Move this timetable to archived status manually (admin action).
     *
     * Typically called when an admin wants to retire an active timetable
     * without replacing it. Prefer activate() on the replacement instead,
     * which archives automatically.
     *
     * @throws \RuntimeException if the timetable is already archived.
     */
    public function archive(): void
    {
        if ($this->isArchived()) {
            throw new \RuntimeException(
                "Timetable \"{$this->title}\" is already archived."
            );
        }

        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Mark this timetable as having been auto-generated.
     * Called by TimetableGeneratorService after a successful generation run.
     *
     * @param int $generatedByUserId The user who triggered generation.
     */
    public function markGenerated(int $generatedByUserId): void
    {
        $this->update([
            'generated_at' => now(),
            'generated_by' => $generatedByUserId,
        ]);
    }

    /**
     * Clear all auto-generated (non-manually-placed) slots for this timetable.
     * Used before a re-generation run to wipe stale auto-assigned slots
     * while preserving manually-placed ones (is_manually_placed = true).
     *
     * Returns the number of slots removed.
     */
    public function clearAutoGeneratedSlots(): int
    {
        return $this->slots()
            ->where('is_manually_placed', false)
            ->delete(); // soft delete
    }

    /**
     * Clear all conflict records for this timetable.
     * Called at the start of each generation run so old conflicts don't
     * accumulate across multiple re-generations.
     */
    public function clearConflicts(): int
    {
        return $this->conflicts()->delete();
    }

    /**
     * Total number of lesson slots assigned in this timetable.
     * Used in the timetable list view as a progress indicator.
     */
    public function slotCount(): int
    {
        return $this->slots()->whereNull('deleted_at')->count();
    }

    /**
     * The working days configured for this timetable (based on day schedules).
     * Returns an array of ISO day-of-week integers: e.g. [1, 2, 3, 4, 5]
     *
     * @return array<int>
     */
    public function workingDays(): array
    {
        return $this->daySchedules()
            ->pluck('day_of_week')
            ->sort()
            ->values()
            ->toArray();
    }
}
