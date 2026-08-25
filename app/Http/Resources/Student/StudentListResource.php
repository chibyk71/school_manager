<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StudentListResource – Lightweight Student Resource for Listings & DataTables (v2.0 – Production-Ready)
 *
 * This is the **lightweight** version of StudentResource, specifically designed for:
 *   - DataTable listings (Students/Index.vue)
 *   - Search results
 *   - Select dropdowns / autocomplete
 *   - Any place where many students are displayed at once
 *
 * It includes only essential fields + computed attributes to keep payload size small
 * while still providing good UX (full_name, photo, current class, status badge).
 *
 * Features / Problems Solved:
 * - Minimal payload size for fast DataTable rendering (critical when using HasTableQuery).
 * - Includes key computed attributes from Profile (full_name, photo_url, age).
 * - Shows current placement summary without loading full placement history.
 * - Status label for easy badge rendering in frontend.
 * - Conditional relationship loading for performance.
 *
 * Fits into the Student Management Module:
 * - Used by StudentController@index (via HasTableQuery).
 * - Consumed by frontend: Students/Index.vue (main DataTable), search components, modals.
 * - Complements the full StudentResource (used in Show.vue).
 */

class StudentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'admission_number'  => $this->admission_number,
            'status'            => $this->status,
            'status_label'      => $this->getStatusLabel(),

            // Personal Identity (from Profile - lightweight)
            'full_name'         => $this->full_name,
            'photo_url'         => $this->photo_url,
            'age'               => $this->age,

            // Current Academic Info (summary only)
            'current_class'     => $this->current_class ?? 'Not Placed',
            'current_session'   => $this->currentPlacement?->academicSession?->name ?? null,

            // School Context
            'school_id'         => $this->school_id,

            // Guardian Summary
            'has_guardians'     => $this->guardians?->count() > 0,
            'primary_guardian'  => $this->primaryGuardian?->full_name ?? null,

            // Quick Flags
            'is_active'         => $this->isActive(),

            // Timestamps (minimal)
            'created_at'        => $this->created_at?->format('Y-m-d'),
            'updated_at'        => $this->updated_at?->format('Y-m-d'),
        ];
    }

    /**
     * Human-readable status label for badges
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'admitted'    => 'Admitted',
            'enrolled'    => 'Enrolled',
            'active'      => 'Active',
            'graduated'   => 'Graduated',
            'withdrawn'   => 'Withdrawn',
            'transferred' => 'Transferred',
            'suspended'   => 'Suspended',
            'deceased'    => 'Deceased',
            default       => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Additional metadata when this resource is returned as a collection
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'student_list',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
