<?php

namespace App\Models\Promotion;

use App\Models\Academic\ClassSection;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student row inside a promotion batch.
 *
 * recommendation is immutable after population.
 * final_decision is the optional human override (null = recommendation stands).
 */
class PromotionStudent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'promotion_students';

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

    protected $casts = [
        'average_score' => 'decimal:2',
        'failed_subjects_count' => 'integer',
        'total_subjects_count' => 'integer',
        'attendance_percentage' => 'decimal:2',
        'overridden_at' => 'datetime',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function promotionBatch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function currentClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'current_class_section_id');
    }

    public function nextClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'next_class_section_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function getFinalOutcomeAttribute(): string
    {
        return $this->final_decision ?? $this->recommendation;
    }

    public function isOverridden(): bool
    {
        return $this->final_decision !== null;
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function getRecommendationLabelAttribute(): string
    {
        return match ($this->recommendation) {
            'promote'    => 'Promote',
            'repeat'     => 'Repeat',
            'graduate'   => 'Graduate',
            'incomplete' => 'Incomplete',
            default      => ucfirst((string) $this->recommendation),
        };
    }

    public function getOutcomeLabelAttribute(): string
    {
        return match ($this->final_outcome) {
            'promote'    => 'Promote',
            'repeat'     => 'Repeat',
            'graduate'   => 'Graduate',
            'incomplete' => 'Incomplete',
            default      => ucfirst((string) $this->final_outcome),
        };
    }

    public function hasProcessingError(): bool
    {
        return filled($this->processing_error);
    }
}
