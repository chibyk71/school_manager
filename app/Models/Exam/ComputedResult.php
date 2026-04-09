<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ComputedResult
 *
 * Aggregated, ranked result for one student in one exam.
 * Written by ResultComputationService; read by ExamResultsController and ReportCardController.
 *
 * Design decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - `subject_breakdown` is a frozen JSON snapshot taken at computation time.
 *   It contains everything needed to render a report card: per-subject component
 *   scores, total, grade_code, remark, and class stats. It NEVER recomputes from
 *   live data at read time — this guarantees report cards remain stable after
 *   approval even if grades or templates are later modified.
 *
 * - `is_final` is set to true when the exam reaches `results_approved` status.
 *   Once true, no code path should modify this row's scores/position.
 *   Remarks (class_teacher_remark, principal_remark) can still be updated because
 *   they don't affect academic standings.
 *
 * - `position_in_class` uses 1-based ranking with ties (e.g. two students tied
 *   at 1st both get 1, next student gets 3). Set by ResultComputationService.
 *
 * - `promotion_status` is nullable until PromotionService runs (separate module).
 *   Possible values: 'promoted', 'held_back', 'pending'.
 *
 * @property string        $id
 * @property string        $exam_id
 * @property string        $student_id
 * @property string        $class_section_id
 * @property float         $total_score_obtained
 * @property float         $total_score_possible
 * @property float         $average_score
 * @property int           $subjects_count
 * @property int           $subjects_scored
 * @property int           $subjects_passed
 * @property int           $subjects_failed
 * @property int|null      $position_in_class
 * @property int|null      $position_in_level
 * @property int|null      $class_size
 * @property array         $subject_breakdown   frozen JSON snapshot
 * @property string|null   $class_teacher_remark
 * @property string|null   $principal_remark
 * @property string|null   $promotion_status
 * @property bool          $is_final
 * @property \Carbon\Carbon|null $computed_at
 */
class ComputedResult extends Model
{
    protected $table = 'computed_results';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'exam_id',
        'student_id',
        'class_section_id',
        'total_score_obtained',
        'total_score_possible',
        'average_score',
        'subjects_count',
        'subjects_scored',
        'subjects_passed',
        'subjects_failed',
        'position_in_class',
        'position_in_level',
        'class_size',
        'subject_breakdown',
        'class_teacher_remark',
        'principal_remark',
        'promotion_status',
        'is_final',
        'computed_at',
    ];

    protected $casts = [
        'subject_breakdown'    => 'array',
        'total_score_obtained' => 'float',
        'total_score_possible' => 'float',
        'average_score'        => 'float',
        'subjects_count'       => 'integer',
        'subjects_scored'      => 'integer',
        'subjects_passed'      => 'integer',
        'subjects_failed'      => 'integer',
        'position_in_class'    => 'integer',
        'position_in_level'    => 'integer',
        'class_size'           => 'integer',
        'is_final'             => 'boolean',
        'computed_at'          => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        // Adjust class path to your student model
        return $this->belongsTo(\App\Models\Student\Student::class, 'student_id');
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\ClassSection::class, 'class_section_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Human-readable rank string: "1st", "2nd", "3rd", "4th" ...
     */
    public function getPositionLabelAttribute(): string
    {
        $pos = $this->position_in_class;
        if (!$pos) return '—';

        $suffix = match (true) {
            $pos % 100 >= 11 && $pos % 100 <= 13 => 'th',
            $pos % 10 === 1 => 'st',
            $pos % 10 === 2 => 'nd',
            $pos % 10 === 3 => 'rd',
            default         => 'th',
        };

        return "{$pos}{$suffix}";
    }

    /**
     * Whether this result passed (no failed subjects).
     */
    public function getIsPassAttribute(): bool
    {
        return $this->subjects_failed === 0 && $this->subjects_scored > 0;
    }

    /**
     * Percentage score (0–100).
     */
    public function getPercentageAttribute(): float
    {
        if (!$this->total_score_possible) return 0;
        return round(($this->total_score_obtained / $this->total_score_possible) * 100, 2);
    }

    // ─── Query Scopes ─────────────────────────────────────────────────────────

    /**
     * Only final (approved) results.
     */
    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    /**
     * Filter to a specific class section.
     */
    public function scopeForSection($query, string $sectionId)
    {
        return $query->where('class_section_id', $sectionId);
    }

    /**
     * Order by class position ascending (1st, 2nd, 3rd ...).
     */
    public function scopeByPosition($query)
    {
        return $query->orderBy('position_in_class')->orderBy('average_score', 'desc');
    }
}
