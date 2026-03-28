<?php

namespace App\Models\Academic;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ClassSection — the "arm" or "stream" within a class level.
 *
 * Represents the actual teaching group / classroom where students sit,
 * teachers teach, and attendance/results are recorded. Sits one level
 * below ClassLevel in the academic hierarchy:
 *
 *   School → SchoolSection → ClassLevel → ClassSection
 *   e.g.,   School → JSS   → JSS 1     → JSS 1A
 *
 * ── Naming Strategy ──────────────────────────────────────────────────────────
 * `name`         — Arm label only: "A", "B", "Diamond", "Gold"
 * `display_name` — Full label: "JSS 1A", "Primary 2 Diamond"
 *                  Stored for performance; auto-computed by getDisplayNameAttribute()
 *                  when null. Admin can override after bulk generate.
 *
 * ── Persistence ──────────────────────────────────────────────────────────────
 * Sections are PERMANENT structures. They persist across academic sessions.
 * An empty section (no enrolled students) is perfectly valid — e.g., a new
 * section created before the session starts.
 * Student enrollment per session is tracked in student_class_section_pivot.
 *
 * ── Traits Used ──────────────────────────────────────────────────────────────
 * - BelongsToSchool   global scope auto-filters all queries to the active school;
 *                     also auto-assigns school_id on creation
 * - HasTableQuery     powers the AdvancedDataTable with Purity filter/sort support,
 *                     global search, hybrid client/server mode, and column definitions
 * - Filterable        Laravel Purity — allows ?filters[name][$contains]=... queries
 * - Sortable          Laravel Purity — allows ?sort=name:asc queries
 * - SoftDeletes       archive rather than destroy; force-delete cascades
 *                     to enrollment pivot and subject assignment records
 * - LogsActivity      Spatie activity log — tracks all changes for audit trail
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property string      $id                  UUID
 * @property string      $school_id           UUID — multi-tenant anchor
 * @property string      $class_level_id      UUID — parent class level
 * @property string      $name                Arm label: "A", "B", "Diamond"
 * @property string|null $display_name        Full label: "JSS 1A" — stored, overridable
 * @property string|null $room                Physical room reference
 * @property int         $capacity            Max students (0 = uncapped)
 * @property string|null $form_teacher_id     UUID — nullable staff reference
 * @property int         $sort_order          Display order (10-gap convention)
 * @property string      $status              'active' | 'inactive'
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * ── Computed / Appended ───────────────────────────────────────────────────────
 * @property-read string $display_name_computed  "JSS 1A" — falls back if display_name is null
 * @property-read int    $students_count          Count of currently enrolled students
 * @property-read bool   $is_at_capacity          Whether capacity limit is reached
 *
 * ── Relationships ─────────────────────────────────────────────────────────────
 * @property-read ClassLevel                   $classLevel
 * @property-read Staff|null                   $formTeacher
 * @property-read \Illuminate\Database\Eloquent\Collection $students
 * @property-read \Illuminate\Database\Eloquent\Collection $teacherSubjectAssignments
 */
class ClassSection extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;
    use Filterable;
    use Sortable;

    // ──────────────────────────────────────────────────────────────────────────
    // Model Configuration
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The table associated with the model.
     * Explicit declaration prevents Laravel from guessing "class_section" (wrong).
     */
    protected $table = 'class_sections';

    /**
     * The primary key type. UUIDs are used throughout this application.
     */
    protected $keyType = 'string';

    /**
     * Disable auto-incrementing since we use UUIDs.
     */
    public $incrementing = false;

    /**
     * Mass-assignable attributes.
     *
     * Note: school_id is intentionally included so that BulkInsert (used by
     * ClassSectionService::bulkGenerate) can set it explicitly. The
     * BelongsToSchool boot hook also sets it automatically on ::create(),
     * making it safe either way.
     *
     * form_teacher_id: nullable assignment; included so the "assign form teacher"
     * endpoint can update it via $section->update(['form_teacher_id' => $id]).
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'name',
        'display_name',
        'room',
        'capacity',
        'form_teacher_id',
        'sort_order',
        'status',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'capacity' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Appended computed properties included in all array/JSON serializations.
     *
     * students_count is NOT appended by default — it would trigger an N+1 query
     * on every serialization. Use withCount('students') at the query level instead
     * and access it as $section->students_count (Eloquent sets it automatically).
     *
     * display_name_computed IS appended because it's a pure string derivation
     * (no extra query) and the frontend always needs a displayable label.
     */
    protected $appends = [
        'display_name_computed',
        'is_at_capacity',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // HasTableQuery Configuration
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Columns that are NEVER sent to the frontend DataTable.
     * Sensitive / internal fields that should not appear in column definitions.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'form_teacher_id',  // exposed via relationship, not raw UUID
        'deleted_at',
    ];

    /**
     * Columns that ARE sent to the frontend but are HIDDEN by default.
     * Users can toggle them via the column visibility menu.
     *
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'room',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Fields used for global free-text search (the search box on the DataTable).
     * Only text-type fields are meaningful here.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'display_name',
        'room',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Activity Log Configuration
    // ──────────────────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Class section \"{$this->getDisplayNameComputedAttribute()}\" was {$eventName}"
            );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get the full display name, using the stored value when available and
     * computing it as a fallback when display_name is null.
     *
     * Examples:
     *   stored "JSS 1A"        → "JSS 1A"
     *   null + level "JSS 1"   → "JSS 1A"   (computed from classLevel.name + " " + name)
     *   null + no level loaded → "A"         (graceful fallback — relation not eager-loaded)
     *
     * The computed value is NEVER written back to the DB from this accessor.
     * ClassSectionService::bulkGenerate() writes it once at creation time.
     *
     * @return string
     */
    public function getDisplayNameComputedAttribute(): string
    {
        // Use stored value if present
        if (!empty($this->display_name)) {
            return $this->display_name;
        }

        // Compute from eager-loaded class level (no extra query)
        if ($this->relationLoaded('classLevel') && $this->classLevel !== null) {
            return trim($this->classLevel->name . ' ' . $this->name);
        }

        // Graceful fallback — relation not loaded (e.g., called on a bare model)
        return $this->name;
    }

    /**
     * Whether this section has reached its configured student capacity.
     *
     * Returns false when capacity is 0 (meaning uncapped / not configured).
     * Requires students_count to be loaded via withCount('students').
     * If not loaded, returns false rather than triggering an N+1 query.
     *
     * @return bool
     */
    public function getIsAtCapacityAttribute(): bool
    {
        if ($this->capacity === 0) {
            return false; // Not configured — never "at capacity"
        }

        // Only check if students_count is already loaded (avoids N+1)
        if (isset($this->attributes['students_count'])) {
            return $this->attributes['students_count'] >= $this->capacity;
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The ClassLevel this section belongs to (e.g., "JSS 1").
     *
     * Always eager-load this when building lists — display_name_computed
     * depends on it for the fallback computation.
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class, 'class_level_id');
    }

    /**
     * The staff member assigned as form teacher for this section.
     * Returns null if no teacher is assigned yet.
     */
    public function formTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'form_teacher_id');
    }

    /**
     * All students ever enrolled in this section across any session.
     *
     * Includes historical enrollments. Use the currentStudents() method
     * or add a wherePivot filter when you need only the current session.
     *
     * The pivot carries: academic_session_id, is_current, enrolled_at, left_at.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_class_section_pivot',
            'class_section_id',
            'student_id'
        )
            ->withPivot([
                'academic_session_id',
                'is_current',
                'enrolled_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    /**
     * Only students who are CURRENTLY enrolled in this section.
     *
     * Uses the is_current flag rather than date comparisons for O(1) performance.
     * Scoped to the active academic session for additional safety.
     */
    public function currentStudents(): BelongsToMany
    {
        return $this->students()
            ->wherePivot('is_current', true);
    }

    /**
     * All teacher-subject assignments for this section.
     *
     * Used by:
     * - Subject Assignments tab on the section detail page
     * - Timetable module (valid teacher-subject pairings)
     * - Results module (authorization: can this teacher enter results here?)
     */
    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherClassSectionSubject::class,
            'class_section_id'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Filter sections belonging to a specific class level.
     *
     * Used by the DataTable when filtering by class level (URL param or UI filter).
     * Purity also handles this via ?filters[class_level_id][$eq]=uuid,
     * but this scope provides a typed, IDE-friendly alternative for service code.
     *
     * @param  Builder $query
     * @param  string  $classLevelId  UUID of the class level
     */
    public function scopeForClassLevel(Builder $query, string $classLevelId): Builder
    {
        return $query->where('class_level_id', $classLevelId);
    }

    /**
     * Filter sections belonging to a school section (division like JSS, Primary).
     *
     * Requires joining through class_levels. Acceptable cost given the small
     * dataset (<100 class sections per school).
     *
     * @param  Builder $query
     * @param  string  $schoolSectionId  UUID of the school section
     */
    public function scopeForSchoolSection(Builder $query, string $schoolSectionId): Builder
    {
        return $query->whereHas(
            'classLevel',
            fn(Builder $q) => $q->where('school_section_id', $schoolSectionId)
        );
    }

    /**
     * Filter to active sections only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Order sections by their configured sort order, then alphabetically by name
     * as a stable tiebreaker when sort_order values are equal (common during
     * bulk generate before manual reordering).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Include sections that have available capacity for enrollment.
     * Sections with capacity = 0 are always included (uncapped).
     *
     * Requires students_count to be loaded via withCount('students')
     * before applying this scope — otherwise uses a subquery.
     */
    public function scopeWithAvailableCapacity(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            // Always include uncapped sections
            $q->where('capacity', 0)
                // Or sections where enrolled count < configured capacity
                ->orWhereRaw(
                    'capacity > (
                        SELECT COUNT(*) FROM student_class_section_pivot
                        WHERE class_section_id = class_sections.id
                        AND is_current = 1
                    )'
                );
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper Methods
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get the current number of enrolled students.
     *
     * Prefers the already-loaded withCount result to avoid an extra query.
     * Falls back to a direct count if not loaded.
     *
     * @return int
     */
    public function getCurrentEnrollmentCount(): int
    {
        // Use withCount result if available (the preferred path)
        if (isset($this->attributes['students_count'])) {
            return (int) $this->attributes['students_count'];
        }

        // Direct count as fallback (triggers one query)
        return $this->currentStudents()->count();
    }

    /**
     * Get remaining capacity slots.
     * Returns null when capacity is not configured (0 = unlimited).
     *
     * @return int|null
     */
    public function getRemainingCapacity(): ?int
    {
        if ($this->capacity === 0) {
            return null; // Unlimited
        }

        return max(0, $this->capacity - $this->getCurrentEnrollmentCount());
    }

    /**
     * Whether this section is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
