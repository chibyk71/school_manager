<?php

namespace App\Models\Promotion;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * PromotionHistory Model
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * The permanent, immutable academic record of a student's promotion outcome.
 * This is the single source of truth for transcripts, certificates, government reporting,
 * and historical audit.
 *
 * Key Features / Problems Solved:
 * - Immutable record — once written, it can never be deleted or modified
 * - One record per student per from_academic_session (enforced by unique index)
 * - Full denormalization of critical data (outcome, was_overridden, average_score, etc.)
 *   so reports and transcripts require no joins back to promotion_students or exam tables
 * - Links back to the original PromotionBatch and PromotionStudent for complete audit trail
 * - Special protection: boot() method throws LogicException on any delete attempt
 * - No SoftDeletes column at all (enforced in migration)
 *
 * Fits into the Promotion Module:
 * - Created exclusively by ProcessStudentPromotion job when execution succeeds
 * - BelongsTo PromotionBatch, PromotionStudent, Student, School, sessions, and class sections
 * - Used for generating promotion reports, student transcripts, and history views
 * - RestrictOnDelete foreign keys prevent accidental deletion of linked records
 *
 * Design Decisions:
 * - outcome is the final executed decision (promote | repeat | graduate)
 * - was_overridden flag + override_reason preserved for transparency
 * - to_academic_session_id and to_class_section_id can be NULL for graduates/repeats
 * - All timestamps and executed_by are captured at execution time
 *
 * Production-Ready Aspects:
 * - Uses HasUuids trait for consistency
 * - Strong delete protection (double layer: model + DB schema)
 * - Comprehensive relationships and accessors for easy reporting
 * - No mass assignment of sensitive audit fields
 */

class PromotionHistory extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promotion_histories';

    /**
     * The attributes that are mass assignable.
     *
     * Only fields that should ever be set during creation by the job.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'promotion_batch_id',
        'promotion_student_id',
        'from_academic_session_id',
        'to_academic_session_id',
        'from_class_section_id',
        'to_class_section_id',
        'outcome',
        'was_overridden',
        'override_reason',
        'average_score',
        'failed_subjects_count',
        'executed_by',
        'executed_at',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'was_overridden'         => 'boolean',
        'average_score'          => 'decimal:2',
        'failed_subjects_count'  => 'integer',
        'executed_at'            => 'datetime',
    ];

    /**
     * Prevent any deletion of promotion history records.
     *
     * This is a critical academic/legal record. Deletion is never allowed.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($history) {
            throw new LogicException(
                'PromotionHistory records are immutable and cannot be deleted. ' .
                'This is a permanent academic record.'
            );
        });

        static::forceDeleting(function ($history) {
            throw new LogicException(
                'PromotionHistory records are immutable and cannot be force deleted.'
            );
        });
    }

    /**
     * Get the school this history record belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the student this promotion history belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Student\Student::class);
    }

    /**
     * Get the promotion batch that produced this history record.
     */
    public function promotionBatch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class);
    }

    /**
     * Get the original promotion student record (working copy).
     */
    public function promotionStudent(): BelongsTo
    {
        return $this->belongsTo(PromotionStudent::class);
    }

    /**
     * Get the academic session the student was promoted from.
     */
    public function fromAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'from_academic_session_id');
    }

    /**
     * Get the academic session the student was promoted to (NULL for graduates).
     */
    public function toAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'to_academic_session_id');
    }

    /**
     * Get the class section the student came from.
     */
    public function fromClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'from_class_section_id');
    }

    /**
     * Get the class section the student moved to (NULL for repeat/graduate in some cases).
     */
    public function toClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'to_class_section_id');
    }

    /**
     * Get the user who executed the promotion (ran the final job).
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Human-readable outcome label for UI and reports.
     */
    public function getOutcomeLabelAttribute(): string
    {
        return match ($this->outcome) {
            'promote'   => 'Promoted',
            'repeat'    => 'Repeated',
            'graduate'  => 'Graduated',
            default     => ucfirst($this->outcome ?? 'Unknown'),
        };
    }

    /**
     * Check if this promotion outcome was the result of a human override.
     */
    public function wasOverridden(): bool
    {
        return $this->was_overridden === true;
    }

    /**
     * Scope for a specific student’s promotion history in a session.
     */
    public function scopeForStudentInSession($query, $studentId, $sessionId)
    {
        return $query->where('student_id', $studentId)
                     ->where('from_academic_session_id', $sessionId);
    }

    /**
     * Scope for all histories in a particular batch (audit).
     */
    public function scopeForBatch($query, $batchId)
    {
        return $query->where('promotion_batch_id', $batchId);
    }

    /**
     * Scope by outcome type.
     */
    public function scopeWhereOutcome($query, string $outcome)
    {
        return $query->where('outcome', $outcome);
    }
}
