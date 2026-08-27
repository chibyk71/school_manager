<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => (string) $this->status,
            'status_label' => $this->status_label,
            'progress_percentage' => $this->progress_percentage,

            'total_students' => $this->total_students,
            'processed_students' => $this->processed_students,
            'failed_students' => $this->failed_students,
            'completed_with_errors' => (bool) data_get($this->metadata, 'completed_with_errors', false),

            'academic_session' => [
                'id' => $this->academicSession?->id,
                'name' => $this->academicSession?->name,
            ],

            'initiated_by' => $this->whenLoaded('initiatedBy', fn () => [
                'id' => $this->initiatedBy->id,
                'name' => $this->initiatedBy->name,
            ]),

            'approved_by' => $this->whenLoaded('approvedBy', fn () => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),

            'executed_by' => $this->whenLoaded('executedBy', fn () => [
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
