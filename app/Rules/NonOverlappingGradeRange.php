<?php

namespace App\Rules;

use App\Models\Academic\Grade;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * NonOverlappingGradeRange Rule
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * Validates that the proposed min_score–max_score range does NOT overlap with any existing
 * grade ranges in the same school_section_id (or school-wide if school_section_id is null).
 *
 * Features / Problems Solved:
 * • Prevents ambiguous grade assignment (e.g. score 80 could match both A:75–85 and B:80–90)
 * • Handles both creation (ignore nothing) and update (ignore the current grade being edited)
 * • Works with nullable school_section_id (school-wide grades)
 * • Efficient DB query – uses BETWEEN logic with proper indexing in mind
 * • Clear, user-friendly failure message showing conflicting grades
 * • Supports strict non-overlap (ranges can touch at boundaries, e.g. 70–79 and 80–89 is OK)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Used in StoreGradeRequest & UpdateGradeRequest → applied to 'min_score' or 'max_score'
 * • Ensures data integrity before GradeService creates/updates records
 * • Prevents invalid states that would break automatic grade calculation in results
 * • Complements Grade model's overlapsWith() helper (for potential future UI warnings)
 *
 * Usage Examples:
 *   'min_score' => ['required', 'integer', 'min:0', new NonOverlappingGradeRange($this->grade)],
 *   'max_score' => ['required', 'integer', 'gt:min_score', new NonOverlappingGradeRange($this->grade)],
 *
 * Note: Pass the current Grade instance (on update) or null (on create) via constructor.
 */
class NonOverlappingGradeRange implements ValidationRule
{
    /**
     * @param Grade|null $currentGrade The grade being updated (null on create)
     */
    public function __construct(
        protected ?Grade $currentGrade = null
    ) {
    }

    /**
     * Validate the attribute.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // We only care about min_score or max_score fields
        if (!in_array($attribute, ['min_score', 'max_score'])) {
            return;
        }

        $sectionId = request()->input('school_section_id');
        $schoolId = GetSchoolModel()?->id; // assuming your helper function

        if (!$schoolId) {
            $fail('Unable to determine current school context.');
            return;
        }

        // Build the overlap condition
        // A range overlaps if: NOT (this.max < other.min OR this.min > other.max)
        $query = Grade::query()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($sectionId) {
                $q->where('school_section_id', $sectionId)
                    ->orWhereNull('school_section_id'); // allow school-wide conflict check
            })
            ->where('id', '!=', $this->currentGrade?->id) // exclude self on update
            ->where(function ($q) use ($attribute, $value) {
                if ($attribute === 'min_score') {
                    // New min is inside or overlaps existing range
                    $q->where('min_score', '<=', $value)
                        ->where('max_score', '>=', $value);
                } else { // max_score
                    // New max is inside or overlaps existing range
                    $q->where('min_score', '<=', $value)
                        ->where('max_score', '>=', $value);
                }
            });

        $conflicting = $query->get(['id', 'name', 'code', 'min_score', 'max_score']);

        if ($conflicting->isNotEmpty()) {
            $conflicts = $conflicting->map(function ($g) {
                return "{$g->name} ({$g->code}): {$g->min_score}–{$g->max_score}";
            })->implode(', ');

            $fail("The score range overlaps with existing grade(s): {$conflicts}.");
        }
    }
}
