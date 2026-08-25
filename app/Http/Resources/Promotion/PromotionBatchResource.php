<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PromotionBatchResource
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * JSON resource for PromotionBatch model.
 * Used for both Inertia pages and API responses (DataTable, modals, show page).
 *
 * Features:
 * - Includes computed attributes (progress_percentage, status_label)
 * - Loads key relationships with minimal data (academicSession, initiatedBy, approvedBy)
 * - Ready for AdvancedDataTable via HasTableQuery
 * - Clean, consistent structure across the module
 */

class PromotionBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'progress_percentage' => $this->progress_percentage,

            'total_students' => $this->total_students,
            'processed_students' => $this->processed_students,
            'failed_students' => $this->failed_students,

            'academic_session' => [
                'id' => $this->academicSession?->id,
                'name' => $this->academicSession?->name,
            ],

            'initiated_by' => $this->whenLoaded('initiatedBy', fn() => [
                'id' => $this->initiatedBy->id,
                'name' => $this->initiatedBy->name,
            ]),

            'approved_by' => $this->whenLoaded('approvedBy', fn() => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),

            'executed_by' => $this->whenLoaded('executedBy', fn() => [
                'id' => $this->executedBy->id,
                'name' => $this->executedBy->name,
            ]),

            'approved_at' => $this->approved_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),

            'is_terminal' => $this->isTerminal(),
            'is_editable' => $this->isEditable(),
            'is_ready_for_approval' => $this->isReadyForApproval(),
            'is_approved' => $this->isApproved(),
            'is_executing' => $this->isExecuting(),
            'is_completed' => $this->isCompleted(),
            'is_cancelled' => $this->isCancelled(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
