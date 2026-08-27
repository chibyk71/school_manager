<?php

namespace App\Models\Promotion;

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\User;
use App\States\Promotion\Approved;
use App\States\Promotion\Cancelled;
use App\States\Promotion\Completed;
use App\States\Promotion\Draft;
use App\States\Promotion\Executing;
use App\States\Promotion\Pending;
use App\States\Promotion\PromotionBatchStatus;
use App\States\Promotion\Reviewing;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

class PromotionBatch extends Model
{
    use HasFactory, HasUuids, BelongsToSchool, SoftDeletes, HasTableQuery, LogsActivity, HasStates;

    protected $table = 'promotion_batches';

    protected $fillable = [
        'school_id', 'academic_session_id', 'name', 'description', 'status',
        'initiated_by', 'approved_by', 'approved_at', 'approval_comments',
        'executed_by', 'executed_at', 'total_students', 'processed_students',
        'failed_students', 'metadata',
    ];

    protected $casts = [
        'status' => PromotionBatchStatus::class,
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'metadata' => 'array',
        'total_students' => 'integer',
        'processed_students' => 'integer',
        'failed_students' => 'integer',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function students(): HasMany
    {
        return $this->hasMany(PromotionStudent::class, 'promotion_batch_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PromotionHistory::class, 'promotion_batch_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status instanceof PromotionBatchStatus
            ? $this->status->label()
            : ucfirst((string) $this->status);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_students <= 0) {
            return 0.0;
        }

        return round(($this->processed_students / $this->total_students) * 100, 1);
    }

    public function isTerminal(): bool
    {
        return $this->status instanceof PromotionBatchStatus && $this->status->isTerminal();
    }

    public function isEditable(): bool
    {
        return $this->status instanceof PromotionBatchStatus && $this->status->isEditable();
    }

    public function isReadyForApproval(): bool
    {
        return $this->status instanceof Pending || $this->status instanceof Reviewing;
    }

    public function isApproved(): bool
    {
        return $this->status instanceof Approved;
    }

    public function isExecuting(): bool
    {
        return $this->status instanceof Executing;
    }

    public function isCompleted(): bool
    {
        return $this->status instanceof Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status instanceof Cancelled;
    }

    public function isDraft(): bool
    {
        return $this->status instanceof Draft;
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [Completed::$name, Cancelled::$name]);
    }

    public function scopeReadyForReview($query)
    {
        return $query->whereIn('status', [Pending::$name, Reviewing::$name]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'approved_by', 'approved_at', 'approval_comments',
                'executed_by', 'executed_at', 'total_students',
                'processed_students', 'failed_students', 'metadata',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
