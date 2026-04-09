<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ComputedResultResource
 *
 * Shapes ComputedResult data for the results view, report card, and parent portal.
 *
 * Includes:
 * - Student identity (from eager-loaded profile)
 * - Aggregate scores and position
 * - Full subject breakdown (frozen snapshot — never recomputed at resource level)
 * - Promotion status
 * - Teacher/principal remarks
 *
 * The `subject_breakdown` JSON is returned as-is from the frozen snapshot.
 * This ensures report cards never change after approval, even if grades or
 * templates are later modified.
 *
 * Fits into the module:
 * - ExamResultsController::index() — class results table
 * - ReportCardController::show()   — individual student report card
 * - PromotionService               — reads subjects_failed for promotion logic
 */
class ComputedResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'exam_id'    => $this->exam_id,
            'student_id' => $this->student_id,

            // Student identity (from eager-loaded relation)
            'student' => $this->whenLoaded('student', fn() => [
                'id'               => $this->student->id,
                'full_name'        => $this->student->full_name,
                'admission_number' => $this->student->admission_number,
                'photo_url'        => $this->student->photo_url,
            ]),

            // Class context
            'class_section_id'   => $this->class_section_id,
            'class_section_name' => $this->whenLoaded('classSection', fn() => $this->classSection?->display_name),

            // Aggregate scores
            'total_score_obtained' => $this->total_score_obtained,
            'total_score_possible' => $this->total_score_possible,
            'average_score'        => $this->average_score,

            // Subject counts
            'subjects_count'   => $this->subjects_count,
            'subjects_scored'  => $this->subjects_scored,
            'subjects_passed'  => $this->subjects_passed,
            'subjects_failed'  => $this->subjects_failed,

            // Positions
            'position_in_class' => $this->position_in_class,
            'position_in_level' => $this->position_in_level,
            'class_size'        => $this->class_size,

            // The frozen snapshot — used verbatim for report cards
            'subject_breakdown' => $this->subject_breakdown ?? [],

            // Remarks
            'class_teacher_remark' => $this->class_teacher_remark,
            'principal_remark'     => $this->principal_remark,

            // Promotion
            'promotion_status' => $this->promotion_status,
            'is_final'         => $this->is_final,

            // Metadata
            'computed_at' => $this->computed_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
