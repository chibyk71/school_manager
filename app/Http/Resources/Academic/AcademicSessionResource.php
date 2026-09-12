<?php

namespace App\Http\Resources\Academic;

use App\Http\Resources\Academic\TermResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\ModelStates\State;

/**
 * AcademicSessionResource – API Resource for AcademicSession model
 *
 * Authoritative lifecycle representation: state / state_label only.
 */
class AcademicSessionResource extends JsonResource
{
    /**
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
            'state'        => $this->state instanceof State ? $this->state->getValue() : (string) $this->state,
            'state_label'  => $this->state_label,
            // Phase 2: current operational = ACTIVE or PAUSED (not a stored flag)
            'is_current_operational' => $this->resource->isCurrentOperational(),
            'activated_at' => $this->activated_at?->format('Y-m-d H:i'),
            'closed_at'    => $this->closed_at?->format('Y-m-d H:i'),
            'term_count'   => $this->whenLoaded('terms', fn () => $this->terms->count()),
            'terms'        => TermResource::collection($this->whenLoaded('terms')),
            'created_at'   => $this->when($request->user()?->hasRole('super-admin'), $this->created_at?->toDateTimeString()),
        ];
    }
}
