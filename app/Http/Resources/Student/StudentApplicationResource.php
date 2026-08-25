<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\AcademicSessionResource;
use App\Http\Resources\ClassLevelResource;
use App\Http\Resources\SchoolSectionResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StudentApplicationResource – API Resource for Student Applications (v2.0 – Production-Ready)
 *
 * Transforms a StudentApplication model into a consistent, frontend-friendly JSON structure.
 *
 * This resource is used for:
 *   - Listing applications (Applications/Index.vue DataTable)
 *   - Showing detailed application view (Applications/Show.vue)
 *   - Public tracking page (Apply/Track.vue)
 *
 * Features / Problems Solved:
 * - Clean, predictable structure for frontend consumption.
 * - Includes computed full_name and status badges.
 * - Nested resources for related models (session, section, class level, reviewer).
 * - Handles JSON fields (guardians_data, documents, custom_data) safely.
 * - Conditional loading of relationships to optimize performance.
 * - Ready for both admin and public portal usage.
 *
 * Fits into the Student Management Module:
 * - Returned by ApplicationController@index and @show.
 * - Consumed by frontend: Applications/Index.vue, Applications/Show.vue, and public tracking.
 * - Works with StudentApplication model and StudentApplicationService.
 */

class StudentApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'application_token' => $this->application_token,
            'source' => $this->source,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),

            // Personal Information
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,

            // Academic Intent
            'academic_session' => new AcademicSessionResource($this->whenLoaded('academicSession')),
            'school_section' => new SchoolSectionResource($this->whenLoaded('schoolSection')),
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),

            // Previous School (for transfers)
            'previous_school' => $this->previous_school,
            'previous_class' => $this->previous_class,
            'previous_school_address' => $this->previous_school_address,

            // Guardian Data (raw from form)
            'guardians_data' => $this->guardians_data,

            // Review Information
            'reviewed_by' => new UserResource($this->whenLoaded('reviewer')),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i'),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i'),
            'rejection_reason' => $this->rejection_reason,
            'admin_notes' => $this->admin_notes,

            // Outcome
            'student_id' => $this->student_id,
            'student' => new StudentListResource($this->whenLoaded('student')), // lightweight student resource

            // Supporting Data
            'documents' => $this->documents,
            'custom_data' => $this->custom_data,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'admitted' => 'Admitted',
            'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Additional metadata when this resource is the top level
     */
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
