<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\AcademicSessionResource;
use App\Http\Resources\ClassLevelResource;
use App\Http\Resources\SchoolSectionResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StudentApplicationResource
 *
 * application_token is intentionally omitted – it is a bearer secret returned
 * only once on the public Submitted page, never in listings or staff resources.
 */
class StudentApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'source' => $this->source,
            'status' => $this->canonical_status,
            'status_label' => $this->getStatusLabel(),

            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'nationality' => $this->nationality,
            'state_of_origin' => $this->state_of_origin,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,

            'academic_session' => new AcademicSessionResource($this->whenLoaded('academicSession')),
            'school_section' => new SchoolSectionResource($this->whenLoaded('schoolSection')),
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),

            'previous_school' => $this->previous_school,
            'previous_class' => $this->previous_class,
            'previous_school_address' => $this->previous_school_address,

            'guardians_data' => $this->guardians_data,

            'reviewed_by' => new UserResource($this->whenLoaded('reviewer')),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i'),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i'),
            'rejection_reason' => $this->rejection_reason,
            'admin_notes' => $this->admin_notes,

            'student_id' => $this->student_id,
            'student' => new StudentListResource($this->whenLoaded('student')),

            'documents' => $this->documents,
            'custom_data' => $this->custom_data,
            'custom_field_responses' => $this->when(
                $this->relationLoaded('customFieldResponses'),
                function () {
                    return $this->customFieldResponses->map(function ($response) {
                        return [
                            'name' => $response->customField?->name,
                            'label' => $response->customField?->label,
                            'field_type' => $response->customField?->field_type,
                            'value' => $response->value,
                        ];
                    })->values();
                }
            ),

            'fee_payment_status' => $this->fee_payment_status,
            'fee_payment_reference' => $this->fee_payment_reference,
            'fee_paid_at' => $this->fee_paid_at?->format('Y-m-d H:i'),
            'fee_satisfied' => $this->isApplicationFeeSatisfied(),
            'possible_duplicates_count' => $this->when(
                isset($this->possible_duplicates_count),
                $this->possible_duplicates_count
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i'),
        ];
    }

    private function getStatusLabel(): string
    {
        return match ($this->canonical_status) {
            'draft' => 'Draft',
            'submitted', 'pending' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'student_application',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
