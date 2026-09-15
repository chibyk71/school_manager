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
 * Represents a specific examination event in the system.
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

    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT            => [self::STATUS_PUBLISHED],
        self::STATUS_PUBLISHED        => [self::STATUS_ONGOING, self::STATUS_DRAFT],
        self::STATUS_ONGOING          => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED        => [self::STATUS_RESULTS_APPROVED],
        self::STATUS_RESULTS_APPROVED => [],
    ];

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

    public function isDraft(): bool      { return $this->status === self::STATUS_DRAFT; }
    public function isPublished(): bool  { return $this->status === self::STATUS_PUBLISHED; }
    public function isOngoing(): bool    { return $this->status === self::STATUS_ONGOING; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }
    public function isApproved(): bool   { return $this->status === self::STATUS_RESULTS_APPROVED; }

    public function isEditable(): bool
    {
        if ($this->locked_at !== null) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_PUBLISHED,
            self::STATUS_ONGOING,
            self::STATUS_COMPLETED,
        ], true);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === self::STATUS_RESULTS_APPROVED;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

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

    public function getScoreEntryProgressAttribute(): float
    {
        $total = $this->examResults()->count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->examResults()
            ->whereNotNull('total_score')
            ->count();

        return round(($completed / $total) * 100, 1);
    }

    public function scopeForCurrentTerm(Builder $query): Builder
    {
        $currentTerm = \App\Facades\Academic::currentTerm();
        if (!$currentTerm) {
            return $query->whereRaw('1 = 0');
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('exam');
    }
}
