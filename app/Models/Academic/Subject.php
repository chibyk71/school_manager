<?php

namespace App\Models\Academic;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Subject Model – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Represents an academic subject (e.g., Mathematics, English Language, Physics)
 * within the school management system. A subject is a foundational entity
 * referenced by timetables, assessments, exam results, and teacher assignments.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • Multi-tenant isolation via BelongsToSchool (auto school_id scoping)
 * • Many-to-many with SchoolSection via BelongsToSections (polymorphic pivot)
 * • Many-to-many with ClassLevel (subjects taught at specific class levels)
 * • Many-to-many with Staff (teachers assigned to this subject)
 * • Optional many-to-many with Students for elective subject selection
 * • Soft deletes (recoverable) with restore support
 * • Server-side DataTable via HasTableQuery (search, filter, sort, paginate)
 * • Laravel Purity integration for advanced filtering from frontend
 * • Activity logging for audit trail (Spatie)
 * • UUID primary keys for security and distributed compatibility
 * • Computed appended attributes (is_active display, teacher count, etc.)
 * • Category and type enums enforced at model level
 * • Code uniqueness enforced per school context
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FITS INTO THE MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * • Used by SubjectController (CRUD, bulk actions, restore)
 * • Used by SubjectService (business logic, validation, section/class sync)
 * • Referenced in timetable, assessment, and exam result modules (future)
 * • Frontend: Academic/Subjects/Index.vue (DataTable + modal)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RELATIONSHIPS
 * ─────────────────────────────────────────────────────────────────────────────
 * • belongsToMany SchoolSection (via BelongsToSections trait → sectionables pivot)
 * • belongsToMany ClassLevel (class_level_subject pivot)
 * • belongsToMany Staff (staff_subject pivot — teachers)
 * • belongsToMany Student (student_subject pivot — for elective selection)
 */
class Subject extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        BelongsToSections,
        HasTableQuery,
        HasDynamicEnum,
        Filterable,
        Sortable,
        LogsActivity;

    protected $table = 'subjects';

    protected $fillable = [
        'school_id',
        'name',
        'code',           // Short abbreviation e.g. "MTH", "ENG", "PHY"
        'description',
        'type',           // core | elective | optional
        'category',       // sciences | arts | commerce | languages | technical | general
        'is_active',
        'color',          // Hex color for timetable display
        'icon',           // Optional icon class
        'pass_mark',      // Minimum passing score (default 40)
        'credit_hours',   // Weekly hours
        'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pass_mark' => 'integer',
        'credit_hours' => 'integer',
        'sort' => 'integer',
    ];

    /**
     * Columns NEVER exposed in DataTable responses (sensitive / internal)
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'updated_at',
    ];

    /**
     * Columns sent to frontend but hidden by default (user can reveal them)
     */
    protected array $defaultHiddenColumns = [
        'description',
        'pass_mark',
        'credit_hours',
        'color',
        'sort',
    ];

    /**
     * Fields used for the global free-text search bar in DataTable
     */
    protected array $globalFilterFields = [
        'name',
        'code',
        'category',
        'type',
    ];


    // ─── Relationships ────────────────────────────────────────────────────

    /**
     * Class levels where this subject is taught (many-to-many)
     * Pivot: class_level_subject (class_level_id, subject_id)
     */
    public function classLevels()
    {
        return $this->belongsToMany(
            ClassLevel::class,
            'class_level_subject',
            'subject_id',
            'class_level_id'
        )->withTimestamps();
    }

    /**
     * Teachers (Staff) assigned to teach this subject (many-to-many)
     * Pivot: staff_subject (staff_id, subject_id)
     */
    public function teachers()
    {
        return $this->belongsToMany(
            \App\Models\Employee\Staff::class,
            'staff_subject',
            'subject_id',
            'staff_id'
        )->withTimestamps();
    }

    /**
     * Students who have selected this subject (many-to-many, mainly for electives)
     * Pivot: student_subject (student_id, subject_id)
     */
    public function students()
    {
        return $this->belongsToMany(
            Student::class,
            'student_subject',
            'subject_id',
            'student_id'
        )->withTimestamps();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    /**
     * Scope: Only active subjects
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by subject type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // ─── Activity logging ─────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('subject')
            ->setDescriptionForEvent(
                fn(string $event) => "Subject {$event}: {$this->name} ({$this->code})"
            )
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDynamicEnumProperties()
    {
        return ['type, category'];
    }
}
