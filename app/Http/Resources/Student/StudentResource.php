<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\ProfileResource;
use App\Http\Resources\SchoolResource;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Http\Resources\student\StudentSessionPlacementResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StudentResource – Full Student Detail Resource (v2.0 – Production-Ready)
 *
 * This is the main detailed resource for a single Student record.
 * It includes all important relationships and computed attributes needed for:
 *   - Student profile/show page (Student/Show.vue)
 *   - Detailed modals and side panels
 *   - Admin/teacher detailed views
 *
 * Features / Problems Solved:
 * - Complete student view with personal data from Profile (no duplication).
 * - Includes current placement, full placement history, and guardians with pivot data.
 * - Computed attributes (full_name, age, photo_url, current_class) for easy frontend use.
 * - Conditional loading of heavy relationships to maintain performance.
 * - Status labels and human-readable information.
 *
 * Fits into the Student Management Module:
 * - Returned by StudentController@show.
 * - Consumed by frontend: Students/Show.vue (Overview | Academic | Guardians | etc. tabs).
 * - Works with Student model, ProfileResource, and related placement/guardian resources.
 */

class StudentResource extends JsonResource
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
            'admission_number' => $this->admission_number,
            'admission_date' => $this->admission_date?->format('Y-m-d'),
            'admission_type' => $this->admission_type,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_reason' => $this->status_reason,
            'status_date' => $this->status_date?->format('Y-m-d'),
            'status_until' => $this->status_until?->format('Y-m-d'),

            // Personal Identity (from central Profile)
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'full_name' => $this->full_name,
            'photo_url' => $this->photo_url,
            'age' => $this->age,

            // School Context
            'school' => new SchoolResource($this->whenLoaded('school')),

            // Application Origin
            'application' => new StudentApplicationResource($this->whenLoaded('application')),

            // Current Academic Placement
            'current_placement' => new StudentSessionPlacementResource($this->whenLoaded('currentPlacement')),

            // All Placement History
            'placements' => StudentSessionPlacementResource::collection(
                $this->whenLoaded('sessionPlacements')
            ),

            // Guardians with rich pivot data
            'guardians' => $this->whenLoaded('guardians', function () {
                return $this->guardians->map(function ($guardian) {
                    return [
                        'id' => $guardian->id,
                        'full_name' => $guardian->full_name,
                        'photo_url' => $guardian->photo_url,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        'relationship' => $guardian->pivot->relationship,
                        'is_primary_contact' => (bool) $guardian->pivot->is_primary_contact,
                        'can_pickup' => (bool) $guardian->pivot->can_pickup,
                        'can_access_portal' => (bool) $guardian->pivot->can_access_portal,
                        'is_emergency_contact' => (bool) $guardian->pivot->is_emergency_contact,
                        'emergency_contact_priority' => $guardian->pivot->emergency_contact_priority,
                        'notes' => $guardian->pivot->notes,
                    ];
                });
            }),

            // Additional Fields
            'previous_school' => $this->previous_school,
            'previous_class' => $this->previous_class,
            'transfer_destination' => $this->transfer_destination,
            'transfer_certificate_number' => $this->transfer_certificate_number,
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Human-readable status label
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'admitted' => 'Admitted',
            'enrolled' => 'Enrolled',
            'active' => 'Active',
            'graduated' => 'Graduated',
            'withdrawn' => 'Withdrawn',
            'transferred' => 'Transferred',
            'suspended' => 'Suspended',
            'deceased' => 'Deceased',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Additional metadata
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'student',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
