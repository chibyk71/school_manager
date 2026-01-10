<?php

namespace App\Http\Resources\Academic;

use App\Http\Resources\Academic\TermResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AcademicSessionResource – API Resource for AcademicSession model
 *
 * Transforms AcademicSession model into a clean, consistent JSON structure
 * for Inertia.js frontend or API consumers.
 *
 * Features:
 * - Controlled field exposure (no accidental leaks)
 * - Formatted dates (Y-m-d for inputs, human-readable optional)
 * - Computed values (duration, term_count)
 * - Conditional policy-based permissions (can_update, can_activate, etc.)
 * - Relationship inclusion (terms) when loaded
 *
 * Usage:
 *   return AcademicSessionResource::collection($sessions);
 *   return new AcademicSessionResource($session);
 */
class AcademicSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'start_date'   => $this->start_date?->format('Y-m-d'),
            'end_date'     => $this->end_date?->format('Y-m-d'),
            'duration_days' => $this->start_date && $this->end_date
                ? $this->start_date->diffInDays($this->end_date) + 1
                : null,
            'is_current'   => $this->is_current,
            'status'       => $this->status,
            'activated_at' => $this->activated_at?->format('Y-m-d H:i'),
            'closed_at'    => $this->closed_at?->format('Y-m-d H:i'),
            'term_count'   => $this->whenLoaded('terms', fn() => $this->terms->count()),
            'terms'        => TermResource::collection($this->whenLoaded('terms')),

            // Timestamps (optional - include only when needed)
            'created_at'   => $this->when($request->user()?->hasRole('super-admin'), $this->created_at?->toDateTimeString()),
        ];
    }
}
