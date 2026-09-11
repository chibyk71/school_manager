<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Spatie\ModelStates\State;

/**
 * TermResource – API Resource for Term Model
 *
 * Authoritative lifecycle representation: state / state_label only.
 */
class TermResource extends JsonResource
{
    /**
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
                fn () => $this->start_date->format('M d, Y') . ' – ' . $this->end_date->format('M d, Y')
            ),
            'display_name'          => $this->display_name,
            'state'                 => $this->state instanceof State ? $this->state->getValue() : (string) $this->state,
            'state_label'           => $this->state_label,
            'color'                 => $this->color,
            'ordinal_number'        => $this->ordinal_number,

            'academic_session'      => $this->whenLoaded('academicSession', fn () => [
                'id'   => $this->academicSession->id,
                'name' => $this->academicSession->name,
            ]),

            'can_update'            => Gate::allows('update', $this),
            'can_delete'            => Gate::allows('delete', $this),
            'can_close'             => Gate::allows('close', $this),
            'can_reopen'            => Gate::allows('reopen', $this),

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
