<?php

namespace App\Models\Exam;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Exam Model
 *
 * Represents a specific examination event in the system. An exam is the central anchor
 * for score entry, timetable scheduling, result computation, and report card generation.
 *
 * Status Machine:
 *   draft → published → ongoing → completed → results_approved
 *
 * Key transitions enforced by ExamService:
 *   - draft → published:       admin publishes; teachers can now see and enter scores
 *   - published → ongoing:     exam start_date reached or manually triggered
 *   - ongoing → completed:     all scores entered (or manually forced by admin)
 *   - completed → results_approved: principal/admin approves results; report cards unlock
 *
 * Features / Problems Solved:
 * - `isEditable()`: single method used by all services to check if mutation is allowed
 * - `isLocked()`: checks locked_at timestamp; used to block score entry
 * - `scopeForCurrentTerm()`: commonly needed across controllers
 * - `getApplicableSectionsAttribute()`: resolves which class_sections this exam covers
 *   (all sections of a level if class_section_id is null, or the specific section)
 * - Soft deletes preserve exam records even when sections/levels are archived
 *
 * Fits into the module:
 * - ExamController: full CRUD + status transitions
 * - ScoreEntryController: checks isEditable() before allowing score saves
 * - ResultComputationService: reads this to pull enrolled students
 * - ExamTimetableController: links timetable rows to this exam
 */
class Exam extends Model
{
    use HasFactory, HasUuids, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    protected $table = 'exams';

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'class_level_id',
        'class_section_id',
        'assessment_template_id',
        'name',
        'description',
        'status',
        'exam_start_date',
        'exam_end_date',
        'published_at',
        'results_published_at',
        'locked_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'exam_start_date'         => 'date',
        'exam_end_date'           => 'date',
        'published_at'            => 'datetime',
        'results_published_at'    => 'datetime',
        'locked_at'               => 'datetime',
    ];

    protected array $hiddenTableColumns = ['id', 'school_id', 'deleted_at', 'created_by', 'approved_by'];
    protected array $defaultHiddenColumns = ['description', 'created_at', 'updated_at'];
    protected array $globalFilterFields = ['name', 'description'];

    // ────────────────────────────────────────────────────────────
    // Status Constants
    // ────────────────────────────────────────────────────────────

    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PUBLISHED        = 'published';
    public const STATUS_ONGOING          = 'ongoing';
    public const STATUS_COMPLETED        = 'completed';
    public const STATUS_RESULTS_APPROVED = 'results_approved';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ONGOING,
        self::STATUS_COMPLETED,
        self::STATUS_RESULTS_APPROVED,
    ];

    /** Allowed transitions: current_status → [next_allowed_statuses] */
    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT            => [self::STATUS_PUBLISHED],
        self::STATUS_PUBLISHED        => [self::STATUS_ONGOING, self::STATUS_DRAFT],
        self::STATUS_ONGOING          => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED        => [self::STATUS_RESULTS_APPROVED],
        self::STATUS_RESULTS_APPROVED => [], // Terminal state
    ];

    // ────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────

    public function academicSession()
    {
        return $this->belongsTo(\App\Models\Academic\AcademicSession::class);
    }

    public function term()
    {
        return $this->belongsTo(\App\Models\Academic\Term::class);
    }

    public function classLevel()
    {
        return $this->belongsTo(\App\Models\Academic\ClassLevel::class);
    }

    public function classSection()
    {
        return $this->belongsTo(\App\Models\Academic\ClassSection::class);
    }

    public function assessmentTemplate()
    {
        return $this->belongsTo(AssessmentTemplate::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function computedResults()
    {
        return $this->hasMany(ComputedResult::class);
    }

    public function timetable()
    {
        return $this->hasMany(ExamTimetable::class)->orderBy('exam_date')->orderBy('start_time');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // ────────────────────────────────────────────────────────────
    // Status Helpers
    // ────────────────────────────────────────────────────────────

    public function isDraft(): bool      { return $this->status === self::STATUS_DRAFT; }
    public function isPublished(): bool  { return $this->status === self::STATUS_PUBLISHED; }
    public function isOngoing(): bool    { return $this->status === self::STATUS_ONGOING; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }
    public function isApproved(): bool   { return $this->status === self::STATUS_RESULTS_APPROVED; }

    /**
     * Determines whether scores can be entered/modified for this exam.
     * Score entry is allowed from published through completed, but NOT once results are approved
     * or the exam is explicitly locked.
     */
    public function isEditable(): bool
    {
        if ($this->locked_at !== null) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_PUBLISHED,
            self::STATUS_ONGOING,
            self::STATUS_COMPLETED, // Allow corrections until approved
        ], true);
    }

    /**
     * Checks if the exam is locked (hard lock — no further changes at all).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === self::STATUS_RESULTS_APPROVED;
    }

    /**
     * Checks whether a given status transition is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    // ────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────

    /**
     * Get the class sections this exam applies to.
     * If class_section_id is set → only that section.
     * If only class_level_id is set → all sections of that level.
     * Returns an Eloquent Collection of ClassSection models.
     */
    public function getApplicableSections(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->class_section_id) {
            return \App\Models\Academic\ClassSection::where('id', $this->class_section_id)->get();
        }

        if ($this->class_level_id) {
            return \App\Models\Academic\ClassSection::where('class_level_id', $this->class_level_id)
                ->where('status', 'active')
                ->get();
        }

        return new \Illuminate\Database\Eloquent\Collection();
    }

    /**
     * Get the completion percentage for score entry.
     * Percentage = scores entered / total expected scores × 100
     */
    public function getScoreEntryProgressAttribute(): float
    {
        $total = $this->examResults()->count();
        if ($total === 0) {
            return 0.0;
        }

        // Count rows where total_score is not null (all components entered)
        $completed = $this->examResults()
            ->whereNotNull('total_score')
            ->count();

        return round(($completed / $total) * 100, 1);
    }

    // ────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────

    public function scopeForCurrentTerm(Builder $query): Builder
    {
        $currentTerm = app('academicContext')->currentTerm();
        if (!$currentTerm) {
            return $query->whereRaw('1 = 0'); // No current term → no results
        }

        return $query->where('term_id', $currentTerm->id);
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->whereNull('locked_at')
            ->whereIn('status', [
                self::STATUS_PUBLISHED,
                self::STATUS_ONGOING,
                self::STATUS_COMPLETED,
            ]);
    }

    public function scopeForLevel(Builder $query, string $classLevelId): Builder
    {
        return $query->where('class_level_id', $classLevelId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    // ────────────────────────────────────────────────────────────
    // Activity Logging
    // ────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('exam');
    }
}
