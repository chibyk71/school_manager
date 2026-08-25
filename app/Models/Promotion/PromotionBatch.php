<?php

namespace App\Models\Promotion;

use App\Models\School;
use App\Models\User;
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

/**
 * PromotionBatch Model
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Represents one complete promotion cycle for a school in a specific academic session.
 * Acts as the container/parent for all promotion_students records in that cycle.
 *
 * Key Responsibilities:
 * - Lifecycle management through a strict state machine (draft → pending → reviewing → approved → executing → completed / cancelled)
 * - Tracks who initiated, approved, and executed the batch + timestamps and comments
 * - Maintains progress counters (total_students, processed_students, failed_students) updated by jobs
 * - Stores flexible metadata (cancellation reasons, execution stats, error summaries) as JSON
 * - Soft-deletable so cancelled batches can be archived while preserving history
 *
 * Fits into the Promotion Module:
 * - One batch per school per academic_session (enforced by unique constraint in migration)
 * - HasMany promotion_students (cascades on delete)
 * - HasMany promotion_histories (restrict on delete for audit integrity)
 * - Triggered automatically via TermClosed event listener or manually via PromotionBatchController
 * - All state transitions are guarded and logged via PromotionService::transitionTo()
 *
 * State Machine (enforced in PromotionService):
 * draft → pending (after PopulatePromotionBatch job)
 * pending/reviewing ↔ approved (via promotions.approve permission)
 * approved → executing (via promotions.execute permission)
 * executing → completed (via ProcessStudentPromotion job)
 * pending/reviewing → cancelled (via promotions.cancel permission)
 * completed & cancelled are terminal states
 *
 * Dependencies / Patterns Used:
 * - HasUuids trait (consistent UUID primary keys)
 * - BelongsToSchool trait (multi-tenant scoping + auto school_id assignment)
 * - LogsActivity (Spatie) for full audit trail of status changes, approvals, etc.
 * - Standard Laravel relationships and accessors
 *
 * Production-Ready Features:
 * - Strict type hints and return types
 * - Comprehensive PHPDoc for IDE support and documentation
 * - No direct state mutation — all changes go through PromotionService for validation
 * - Activity logging configured to ignore sensitive fields and log only meaningful changes
 */

class PromotionBatch extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToSchool;
    use SoftDeletes;
    use HasTableQuery;
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promotion_batches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'name',
        'description',
        'status',
        'initiated_by',
        'approved_by',
        'approved_at',
        'approval_comments',
        'executed_by',
        'executed_at',
        'total_students',
        'processed_students',
        'failed_students',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'total_students' => 'integer',
        'processed_students' => 'integer',
        'failed_students' => 'integer',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden from arrays/JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // No sensitive data by default
    ];

    /**
     * Get the school that owns this promotion batch.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the academic session this batch belongs to.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\AcademicSession::class, 'academic_session_id');
    }

    /**
     * Get the user who initiated/created this batch.
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the user who approved this batch (if any).
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who executed this batch (if any).
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * All student records linked to this promotion batch.
     *
     * Note: promotion_students.cascadeOnDelete() is defined in migration.
     */
    public function promotionStudents(): HasMany
    {
        return $this->hasMany(PromotionStudent::class);
    }

    /**
     * All permanent promotion history records created from this batch.
     *
     * Restrict on delete is enforced at DB level.
     */
    public function promotionHistories(): HasMany
    {
        return $this->hasMany(PromotionHistory::class);
    }

    /**
     * Check if the batch is in a terminal state (cannot transition further).
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * Check if the batch can still be modified (review/override phase).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'reviewing'], true);
    }

    /**
     * Check if the batch is ready for approval.
     */
    public function isReadyForApproval(): bool
    {
        return $this->status === 'pending' || $this->status === 'reviewing';
    }

    /**
     * Check if the batch is approved and ready for execution.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the batch is currently being executed.
     */
    public function isExecuting(): bool
    {
        return $this->status === 'executing';
    }

    /**
     * Check if the batch has been successfully completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the batch was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get progress percentage (0-100) for UI display.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_students === 0) {
            return 0;
        }

        return (int) round(($this->processed_students / $this->total_students) * 100);
    }

    /**
     * Get a human-readable status label for UI.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending' => 'Pending Population',
            'reviewing' => 'Under Review',
            'approved' => 'Approved',
            'executing' => 'Executing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Configure activity logging (Spatie).
     *
     * Logs only important changes (status, approvals, execution) while ignoring noise.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'approved_by',
                'approved_at',
                'approval_comments',
                'executed_by',
                'executed_at',
                'processed_students',
                'failed_students',
                'metadata',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                return "Promotion batch {$this->name} was {$eventName}";
            });
    }

    /**
     * Scope to get only non-terminal batches (useful for listings).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope for batches that are ready for review/approval.
     */
    public function scopeReadyForReview($query)
    {
        return $query->whereIn('status', ['pending', 'reviewing']);
    }
}
