<?php

namespace App\Models\Exam;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * ExamResult Model
 *
 * Stores the raw per-component scores for a single student in a single subject for a
 * specific exam. This is the core data-entry model of the entire results module.
 *
 * Key Design:
 * - `scores` JSON holds all component values, keyed by component key
 *   e.g., {"ca1": {"score": 18, "max": 20}, "ca2": {"score": 15, "max": 20}, "exam": {"score": 52, "max": 60}}
 * - `total_score` and `grade_code` are denormalized after computation (not on entry)
 * - `is_absent` / `is_exempted` flags alter how this row is treated in ranking
 * - `locked_at` blocks further edits to this specific subject result (e.g., after verification)
 *
 * Features / Problems Solved:
 * - `getScoreForComponent()`: safe accessor that handles missing keys gracefully
 * - `setComponentScore()`: updates a single component without touching others
 * - `isFullyScored()`: checks if all required components have values
 * - `computeTotal()`: computes total using the template's weights (used by service)
 * - Unique constraint on (exam_id, student_id, subject_id) prevents duplicates
 *
 * Fits into the module:
 * - ScoreEntryController: createOrUpdate these rows via upsert
 * - ResultComputationService: reads scores, applies weights, writes total_score + grade_code
 * - ScoreVerificationService (future): reads entered_by/verified_by for two-level checks
 */
class ExamResult extends Model
{
    use HasFactory, HasUuids, BelongsToSchool;

    protected $table = 'exam_results';

    protected $fillable = [
        'school_id',
        'exam_id',
        'student_id',
        'subject_id',
        'class_section_id',
        'scores',
        'total_score',
        'grade_code',
        'grade_remark',
        'is_absent',
        'is_exempted',
        'remark',
        'entered_by',
        'verified_by',
        'locked_at',
    ];

    protected $casts = [
        'scores'      => 'array',
        'is_absent'   => 'boolean',
        'is_exempted' => 'boolean',
        'total_score' => 'decimal:2',
        'locked_at'   => 'datetime',
    ];

    // ────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(\App\Models\Academic\Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(\App\Models\Academic\Subject::class);
    }

    public function classSection()
    {
        return $this->belongsTo(\App\Models\Academic\ClassSection::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'entered_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    // ────────────────────────────────────────────────────────────
    // Score Accessors & Helpers
    // ────────────────────────────────────────────────────────────

    /**
     * Get the raw score for a specific component key.
     * Returns null if not yet entered.
     */
    public function getScoreForComponent(string $componentKey): ?float
    {
        return $this->scores[$componentKey]['score'] ?? null;
    }

    /**
     * Set a single component score, merging with existing scores JSON.
     * Does NOT save — caller must call save() or use updateComponentScore().
     */
    public function setComponentScore(string $componentKey, ?float $score, float $maxScore): void
    {
        $scores = $this->scores ?? [];
        $scores[$componentKey] = [
            'score'      => $score,
            'max'        => $maxScore,
            'entered_at' => $score !== null ? now()->toISOString() : null,
        ];
        $this->scores = $scores;
    }

    /**
     * Check whether all components required by the template have been scored.
     * Returns false if any component score is null.
     */
    public function isFullyScored(AssessmentTemplate $template): bool
    {
        if ($this->is_absent || $this->is_exempted) {
            return true; // Absent/exempted students don't need scores
        }

        $scores = $this->scores ?? [];

        foreach ($template->componentKeys as $key) {
            if (!isset($scores[$key]) || $scores[$key]['score'] === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute the total weighted score using the template's component weights.
     * Returns null if any component is missing or student is absent/exempted.
     */
    public function computeTotal(AssessmentTemplate $template): ?float
    {
        if ($this->is_absent || $this->is_exempted) {
            return null;
        }

        if (!$this->isFullyScored($template)) {
            return null;
        }

        $total = 0.0;

        foreach ($template->components as $component) {
            $key      = $component['key'];
            $rawScore = $this->scores[$key]['score'] ?? 0;
            $total   += $template->computeWeightedScore($key, $rawScore);
        }

        return round($total, 2);
    }

    /**
     * Check whether this result is locked (cannot be edited).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null || ($this->exam?->isLocked() ?? false);
    }

    // ────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────

    public function scopeForExam(Builder $query, string $examId): Builder
    {
        return $query->where('exam_id', $examId);
    }

    public function scopeForSubject(Builder $query, string $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForSection(Builder $query, string $sectionId): Builder
    {
        return $query->where('class_section_id', $sectionId);
    }

    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeFullyScored(Builder $query): Builder
    {
        return $query->whereNotNull('total_score');
    }

    public function scopeNotLocked(Builder $query): Builder
    {
        return $query->whereNull('locked_at');
    }
}
