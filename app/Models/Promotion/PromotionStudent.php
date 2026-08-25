<?php

namespace App\Models\Promotion;

use App\Models\Academic\ClassSection;
use App\Models\Student\Student;
use App\Models\User;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PromotionStudent Model
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * One row per student per promotion batch.
 * Holds the system-computed recommendation (immutable after population) and
 * optional human override (final_decision).
 *
 * This model serves as the audit trail and working record during the review phase.
 *
 * Key Features / Problems Solved:
 * - Immutable system recommendation (recommendation column) populated by PopulatePromotionBatch job
 * - Optional human override via final_decision + override_reason + overridden_by
 * - Snapshot of academic data (average_score, failed_subjects_count, attendance_percentage)
 *   copied at population time → no heavy joins needed for reports or review UI
 * - Execution tracking (is_processed, processed_at, processing_error) updated by ProcessStudentPromotion job
 * - Unique constraint on (promotion_batch_id, student_id) enforced at DB level
 * - No SoftDeletes — records are tied to the batch lifecycle (batch delete cascades)
 *
 * Fits into the Promotion Module:
 * - BelongsTo PromotionBatch (parent container)
 * - BelongsTo Student (the actual student being promoted)
 * - BelongsTo current_class_section_id and next_class_section_id (for placement)
 * - Used heavily in Review.vue for overrides and in execution job
 * - Data flows: PopulatePromotionBatch → human review/override → ProcessStudentPromotion → PromotionHistory
 * - Supports HasTableQuery trait for AdvancedDataTable in review/index views
 *
 * Production-Ready Aspects:
 * - Uses HasUuids + HasTableQuery traits (consistent with rest of codebase)
 * - Properly configured globalFilterFields, hiddenTableColumns, and defaultHiddenColumns
 * - Strict typing and comprehensive PHPDoc
 * - Accessors for readable status and outcome
 * - Helper methods for common checks (isOverridden, needsOverride, hasProcessingError, etc.)
 * - No direct mutation of recommendation — enforced by service layer
 */

class PromotionStudent extends Model
{
    use HasFactory;
    use HasUuids;
    use HasTableQuery;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promotion_students';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'promotion_batch_id',
        'student_id',
        'current_class_section_id',
        'next_class_section_id',
        'recommendation',
        'average_score',
        'failed_subjects_count',
        'total_subjects_count',
        'attendance_percentage',
        'final_decision',
        'override_reason',
        'overridden_by',
        'overridden_at',
        'is_processed',
        'processed_at',
        'processing_error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'average_score' => 'decimal:2',
        'attendance_percentage' => 'decimal:2',
        'failed_subjects_count' => 'integer',
        'total_subjects_count' => 'integer',
        'overridden_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
    ];

    /**
     * Fields used for global (free-text) search in AdvancedDataTable.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'recommendation',
        'final_decision',
        'student.name',           // assuming student has name or full_name relation
        'student.admission_number',
    ];

    /**
     * Columns that should NEVER be sent to the frontend.
     * Sensitive/internal fields.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'processing_error',       // only shown in debug/admin context
    ];

    /**
     * Columns that ARE sent to the frontend but are hidden by default.
     * Users can toggle them via column chooser.
     *
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'override_reason',
        'overridden_by',
        'overridden_at',
        'total_subjects_count',
        'attendance_percentage',
    ];

    /**
     * Get the promotion batch this student record belongs to.
     */
    public function promotionBatch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class);
    }

    /**
     * Get the student this record refers to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the class section the student is currently in (at time of promotion).
     */
    public function currentClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'current_class_section_id');
    }

    /**
     * Get the class section the student will move to (if promoting).
     */
    public function nextClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'next_class_section_id');
    }

    /**
     * Get the user who overrode the system recommendation (if any).
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * Determine the final outcome that should be used.
     *
     * Returns final_decision if present, otherwise falls back to system recommendation.
     * This is the value that will be executed and recorded in PromotionHistory.
     */
    public function getFinalOutcomeAttribute(): string
    {
        return $this->final_decision ?? $this->recommendation;
    }

    /**
     * Check if this student's recommendation was overridden by a human.
     */
    public function isOverridden(): bool
    {
        return !is_null($this->final_decision);
    }

    /**
     * Check if this student still needs human review/override.
     */
    public function needsOverride(): bool
    {
        return !$this->isOverridden()
            && in_array($this->promotionBatch?->status, ['pending', 'reviewing'], true);
    }

    /**
     * Check if this record has been successfully processed by the execution job.
     */
    public function isProcessed(): bool
    {
        return $this->is_processed === true;
    }

    /**
     * Check if processing failed for this student.
     */
    public function hasProcessingError(): bool
    {
        return !is_null($this->processing_error);
    }

    /**
     * Get a human-readable label for the final outcome.
     */
    public function getOutcomeLabelAttribute(): string
    {
        $outcome = $this->final_outcome;

        return match ($outcome) {
            'promote' => 'Promote',
            'repeat' => 'Repeat',
            'graduate' => 'Graduate',
            default => ucfirst($outcome ?? 'Unknown'),
        };
    }

    /**
     * Get a human-readable label for the system recommendation.
     */
    public function getRecommendationLabelAttribute(): string
    {
        return match ($this->recommendation) {
            'promote' => 'Promote',
            'repeat' => 'Repeat',
            'graduate' => 'Graduate',
            default => ucfirst($this->recommendation ?? 'Unknown'),
        };
    }

    /**
     * Scope to get only students that have not been processed yet.
     */
    public function scopePendingProcessing($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Scope to get students that were overridden.
     */
    public function scopeOverridden($query)
    {
        return $query->whereNotNull('final_decision');
    }

    /**
     * Scope by recommendation type.
     */
    public function scopeWhereRecommendation($query, string $recommendation)
    {
        return $query->where('recommendation', $recommendation);
    }

    /**
     * Scope by final decision (including null = no override).
     */
    public function scopeWhereFinalDecision($query, ?string $decision)
    {
        if ($decision === null) {
            return $query->whereNull('final_decision');
        }

        return $query->where('final_decision', $decision);
    }
}
