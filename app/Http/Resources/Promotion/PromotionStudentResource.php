<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PromotionStudentResource
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * JSON resource for PromotionStudent model.
 * Critical for the Review page and DataTable during human review phase.
 *
 * Features:
 * - Shows both system recommendation and final human decision
 * - Includes academic snapshot (average, failed subjects, attendance)
 * - Clean outcome labels for UI
 * - Conditional loading of relationships
 * - Ready for use with AdvancedDataTable
 */

class PromotionStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,

            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'name' => $this->student->full_name ?? $this->student->name,
                'admission_number' => $this->student->admission_number,
            ]),

            'current_class_section' => $this->whenLoaded('currentClassSection', fn() => [
                'id' => $this->currentClassSection->id,
                'name' => $this->currentClassSection->full_name ?? $this->currentClassSection->name,
            ]),

            'next_class_section' => $this->whenLoaded('nextClassSection', fn() => [
                'id' => $this->nextClassSection?->id,
                'name' => $this->nextClassSection?->full_name ?? $this->nextClassSection?->name,
            ]),

            // System recommendation (immutable)
            'recommendation' => $this->recommendation,
            'recommendation_label' => $this->recommendation_label,

            // Human override (if any)
            'final_decision' => $this->final_decision,
            'final_outcome' => $this->final_outcome,
            'outcome_label' => $this->outcome_label,
            'is_overridden' => $this->isOverridden(),
            'override_reason' => $this->override_reason,
            'overridden_by' => $this->whenLoaded('overriddenBy', fn() => [
                'id' => $this->overriddenBy->id,
                'name' => $this->overriddenBy->name,
            ]),
            'overridden_at' => $this->overridden_at?->toIso8601String(),

            // Academic snapshot
            'average_score' => $this->average_score,
            'failed_subjects_count' => $this->failed_subjects_count,
            'total_subjects_count' => $this->total_subjects_count,
            'attendance_percentage' => $this->attendance_percentage,

            // Execution status
            'is_processed' => $this->is_processed,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'has_processing_error' => $this->hasProcessingError(),
            'processing_error' => $this->when($this->hasProcessingError(), fn() => $this->processing_error),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
