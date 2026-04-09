<?php

namespace App\Models\Exam;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * AssessmentTemplate Model
 *
 * Defines the scoring structure for exams: what components exist (CA1, CA2, Exam, Project),
 * the max score for each, the weight each carries toward the final score, and the pass mark.
 *
 * Features / Problems Solved:
 * - Centralizes exam scoring structure so changing it once affects all linked exams
 * - Allows different templates per school section (Primary vs SS may have different structures)
 * - `components` JSON is the single source of truth for score-entry form rendering
 * - `ensureOnlyOneDefault()` enforces the single-default invariant at the model level
 * - `getTotalWeightAttribute()` validates that component weights sum to 100 (used in validation)
 * - `getComponentByKey()` helper used by score-entry and computation services
 * - SoftDeletes: preserve historical templates used by old exams; never hard-delete
 *
 * Fits into the module:
 * - Exam model belongs to this template
 * - ResultComputationService reads component weights from here
 * - ScoreEntryController uses `components` to render per-component inputs
 * - AssessmentTemplateController CRUD
 */
class AssessmentTemplate extends Model
{
    use HasFactory, HasUuids, BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    protected $table = 'assessment_templates';

    protected $fillable = [
        'school_id',
        'school_section_id',
        'name',
        'description',
        'is_default',
        'is_active',
        'components',
        'total_score',
        'pass_mark',
        'sort_order',
    ];

    protected $casts = [
        'components' => 'array',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'total_score'=> 'integer',
        'pass_mark'  => 'integer',
        'sort_order' => 'integer',
    ];

    protected array $hiddenTableColumns = ['id', 'school_id', 'deleted_at'];
    protected array $defaultHiddenColumns = ['created_at', 'updated_at'];
    protected array $globalFilterFields = ['name', 'description'];

    // ────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────

    public function schoolSection()
    {
        return $this->belongsTo(\App\Models\SchoolSection::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    // ────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ────────────────────────────────────────────────────────────

    /**
     * Get the total weight of all components (should equal 100).
     * Used in validation: if this doesn't return 100, template is invalid.
     */
    public function getTotalWeightAttribute(): float
    {
        return collect($this->components ?? [])->sum('weight_percent');
    }

    /**
     * Find a single component by its key (e.g., 'ca1', 'exam').
     * Used by ScoreEntryController and ResultComputationService.
     */
    public function getComponentByKey(string $key): ?array
    {
        return collect($this->components ?? [])
            ->firstWhere('key', $key);
    }

    /**
     * Get components sorted by sort_order for consistent rendering.
     */
    public function getSortedComponentsAttribute(): array
    {
        return collect($this->components ?? [])
            ->sortBy('sort_order')
            ->values()
            ->toArray();
    }

    /**
     * Get only the component keys (e.g., ['ca1', 'ca2', 'exam']).
     * Used to validate that an exam_result's scores JSON has all required keys.
     */
    public function getComponentKeysAttribute(): array
    {
        return collect($this->components ?? [])->pluck('key')->toArray();
    }

    /**
     * Compute the weighted score contribution for a given component score.
     * E.g., if ca1 has weight 20% and student scores 18/20:
     *   contribution = (18/20) * 20 = 18 (already in raw form for a /100 template)
     *
     * For templates with total_score != 100, we normalize:
     *   normalizedScore = (rawScore / componentMaxScore) * componentWeightPercent
     */
    public function computeWeightedScore(string $componentKey, float $rawScore): float
    {
        $component = $this->getComponentByKey($componentKey);
        if (!$component) {
            return 0.0;
        }

        $maxScore = $component['max_score'] ?? 0;
        if ($maxScore <= 0) {
            return 0.0;
        }

        return ($rawScore / $maxScore) * ($component['weight_percent'] ?? 0);
    }

    // ────────────────────────────────────────────────────────────
    // Boot & Business Rules
    // ────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // When a template is set as default, unset all others for the same school+section
        static::saving(function (self $template) {
            if ($template->is_default && $template->isDirty('is_default')) {
                static::where('school_id', $template->school_id)
                    ->where('school_section_id', $template->school_section_id)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    // ────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSection($query, ?string $sectionId)
    {
        if ($sectionId) {
            return $query->where(function ($q) use ($sectionId) {
                $q->where('school_section_id', $sectionId)
                    ->orWhereNull('school_section_id');
            });
        }

        return $query;
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ────────────────────────────────────────────────────────────
    // Activity Logging
    // ────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('assessment-template');
    }
}
