<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal public-facing Application representation for token status lookup.
 *
 * Intentionally omits staff/internal fields: admin_notes, reviewer, fee references,
 * student linkage, documents, guardians_data, custom_data, internal IDs, etc.
 */
class PublicStudentApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_number' => $this->application_number,
            'full_name' => $this->full_name,
            'status' => $this->canonical_status,
            'status_label' => $this->publicStatusLabel(),
            'academic_session' => $this->whenLoaded('academicSession', function () {
                return [
                    'id' => $this->academicSession?->id,
                    'name' => $this->academicSession?->name,
                ];
            }),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i'),
            'fee_payment_status' => $this->fee_payment_status,
            'fee_satisfied' => $this->isApplicationFeeSatisfied(),
            // Public-facing rejection text only when rejected (no internal notes)
            'rejection_reason' => $this->canonical_status === 'rejected'
                ? $this->rejection_reason
                : null,
        ];
    }

    private function publicStatusLabel(): string
    {
        return match ($this->canonical_status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
            default => ucfirst($this->canonical_status ?? 'Unknown'),
        };
    }
}
