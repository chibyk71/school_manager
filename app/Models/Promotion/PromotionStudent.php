<?php
// app/Models/Promotion/PromotionStudent.php

namespace App\Models\Promotion;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Student;
use App\Models\User;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Temporary record of a student during promotion review.
 *
 * @property int $id
 * @property int $promotion_batch_id
 * @property string $student_id
 * @property string|null $current_class_section_id
 * @property string|null $next_class_section_id
 * @property string $recommendation
 * @property string|null $final_decision
 * @property bool $is_processed
 */
class PromotionStudent extends Model
{
    use HasFactory, LogsActivity, HasTableQuery;

    protected $fillable = [
        'promotion_batch_id',
        'student_id',
        'current_class_section_id',
        'next_class_section_id',
        'recommendation',
        'final_decision',
        'override_reason',
        'overridden_by',
        'failed_subjects_count',
        'average_score',
        'is_processed',
        'processed_at',
    ];

    protected $casts = [
        'average_score' => 'decimal:2',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    protected array $hiddenTableColumns = [
        'promotion_batch_id',
        'overridden_by',
        'processed_at',
    ];

    protected array $globalFilterFields = [
        'recommendation',
        'final_decision',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['final_decision', 'override_reason'])
            ->setDescriptionForEvent(fn(string $event) => "Promotion decision for student was {$event}");
    }

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'current_class_section_id');
    }

    public function nextSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'next_class_section_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}