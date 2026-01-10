<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * TermResource – API Resource for Term Model
 *
 * Transforms the Term model into a clean, consistent, and secure JSON structure
 * for Inertia.js frontend consumption or API endpoints.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Controlled field exposure: only safe, necessary data is sent to frontend
 * • Formatted dates (Y-m-d for inputs, human-readable for display)
 * • Computed/display helpers (period, display_name, duration)
 * • Conditional policy-based frontend permissions (can_update, can_close, can_reopen)
 * • Relationship inclusion: parent session data when loaded
 * • Type-safe & predictable structure for Vue 3 + TypeScript consumption
 * • Prevents leaking sensitive/internal fields (school_id, raw timestamps)
 * • Optimizes for DataTable usage (sortable/filterable fields)
 * • Production-ready: minimal payload, easy to extend, secure by default
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Primary resource used in TermController (index, show)
 * • Returned in Inertia responses (e.g. Terms/Index.vue, Terms/Show.vue)
 * • Used for DataTable population, term cards, modals, and action buttons
 * • Works seamlessly with PrimeVue components (DataTable, Card, Dialog)
 * • Supports conditional UI rendering via can_* flags (e.g. show close button only if allowed)
 * • Integrates with TermClosureController (close/reopen actions use same structure)
 * • Aligns with frontend stack: Inertia props are simple, typed, and ready for Vue composition API
 *
 * Recommended Frontend TypeScript Interface (for reference):
 * interface TermResource {
 *   id: string;
 *   name: string;
 *   short_name: string | null;
 *   description: string | null;
 *   start_date: string;        // '2025-09-01'
 *   end_date: string;
 *   period: string;            // 'Sep 01 – Dec 15, 2025'
 *   display_name: string;
 *   status: 'pending' | 'active' | 'closed';
 *   is_active: boolean;
 *   is_closed: boolean;
 *   color: string | null;
 *   academic_session: { id: string; name: string } | null;
 *   can_update: boolean;
 *   can_delete: boolean;
 *   can_close: boolean;
 *   can_reopen: boolean;
 * }
 */
class TermResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'short_name'            => $this->short_name,
            'description'           => $this->description,
            'start_date'            => $this->start_date?->format('Y-m-d'),
            'end_date'              => $this->end_date?->format('Y-m-d'),
            'period'                => $this->when(
                $this->start_date && $this->end_date,
                fn() => $this->start_date->format('M d, Y') . ' – ' . $this->end_date->format('M d, Y')
            ),
            'display_name'          => $this->display_name,
            'status'                => $this->status,
            'is_active'             => $this->is_active,
            'is_closed'             => $this->is_closed,
            'color'                 => $this->color,
            'ordinal_number'        => $this->ordinal_number,

            // Parent session (only when loaded)
            'academic_session'      => $this->whenLoaded('academicSession', fn() => [
                'id'   => $this->academicSession->id,
                'name' => $this->academicSession->name,
            ]),

            // Policy-based frontend permissions (critical for conditional UI)
            'can_update'            => Gate::allows('update', $this),
            'can_delete'            => Gate::allows('delete', $this),
            'can_close'             => Gate::allows('close', $this),
            'can_reopen'            => Gate::allows('reopen', $this),

            // Timestamps (optional – include only for admins or audit views)
            'created_at'            => $this->when(
                $request->user()?->hasRole('super-admin'),
                $this->created_at?->toDateTimeString()
            ),
            'updated_at'            => $this->when(
                $request->user()?->hasRole('super-admin'),
                $this->updated_at?->toDateTimeString()
            ),
        ];
    }
}
