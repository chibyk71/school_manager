<?php
// app/Models/Promotion/PromotionBatch.php

namespace App\Models\Promotion;

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\User;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PromotionBatch represents one end-of-session promotion cycle.
 *
 * @property int $id
 * @property int $academic_session_id
 * @property int $school_id
 * @property string $name
 * @property string $status
 * @property int|null $principal_id
 * @property \Illuminate\Support\Carbon|null $principal_reviewed_at
 * @property int $total_students
 * @property int $processed_students
 * @property \Illuminate\Support\Carbon|null $executed_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class PromotionBatch extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasTableQuery;

    protected $fillable = [
        'academic_session_id',
        'school_id',
        'name',
        'description',
        'status',
        'principal_id',
        'principal_reviewed_at',
        'principal_comments',
        'executed_at',
        'total_students',
        'processed_students',
    ];

    protected $casts = [
        'principal_reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    protected array $hiddenTableColumns = [
        'school_id',
        'principal_id',
        'principal_comments',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'name',
        'status',
    ];

    protected $appends = [
        'progress_percentage',
        'can_execute',
        'is_approved',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $event) => "Promotion batch '{$this->name}' was {$event}");
    }

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(PromotionStudent::class);
    }

    // ────────────────────────────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────────────────────────────

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_students === 0) return 0;
        return round(($this->processed_students / $this->total_students) * 100, 2);
    }

    public function getCanExecuteAttribute(): bool
    {
        return $this->status === 'approved' && $this->processed_students === 0;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    // ────────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCurrentSchool($query)
    {
        return $query->where('school_id', GetSchoolModel()->id);
    }
}