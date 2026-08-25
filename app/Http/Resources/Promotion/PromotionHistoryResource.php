<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PromotionHistoryResource
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * JSON resource for the immutable PromotionHistory model.
 *
 * This is the **permanent academic record** used for:
 * - Student transcripts
 * - Certificates
 * - Government reporting
 * - Promotion history views
 *
 * Key Features:
 * - Fully denormalized data (no heavy joins needed for reports)
 * - Clear outcome labels and flags (was_overridden)
 * - Safe relationship loading with whenLoaded()
 * - Designed for both Inertia pages and API/report generation
 *
 * Fits into the Promotion Module:
 * - Used in student profile history, transcript generation, and audit views
 * - Created exclusively by ProcessStudentPromotion job
 */

class PromotionHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Core outcome
            'outcome' => $this->outcome,
            'outcome_label' => $this->outcome_label,
            'was_overridden' => $this->was_overridden,
            'override_reason' => $this->when($this->was_overridden, $this->override_reason),

            // Academic snapshot
            'average_score' => $this->average_score,
            'failed_subjects_count' => $this->failed_subjects_count,

            // Session & Class Information
            'from_academic_session' => $this->whenLoaded('fromAcademicSession', fn() => [
                'id' => $this->fromAcademicSession->id,
                'name' => $this->fromAcademicSession->name,
            ]),

            'to_academic_session' => $this->whenLoaded('toAcademicSession', fn() => [
                'id' => $this->toAcademicSession?->id,
                'name' => $this->toAcademicSession?->name,
            ]),

            'from_class_section' => $this->whenLoaded('fromClassSection', fn() => [
                'id' => $this->fromClassSection?->id,
                'name' => $this->fromClassSection?->full_name ?? $this->fromClassSection?->name,
            ]),

            'to_class_section' => $this->whenLoaded('toClassSection', fn() => [
                'id' => $this->toClassSection?->id,
                'name' => $this->toClassSection?->full_name ?? $this->toClassSection?->name,
            ]),

            // Student Information
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'name' => $this->student->full_name ?? $this->student->name,
                'admission_number' => $this->student->admission_number,
            ]),

            // Audit Information
            'executed_by' => $this->whenLoaded('executedBy', fn() => [
                'id' => $this->executedBy->id,
                'name' => $this->executedBy->name,
            ]),

            'executed_at' => $this->executed_at?->toIso8601String(),
            'remarks' => $this->remarks,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
