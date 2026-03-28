<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateClassSectionRequest — validates updates to an existing class section.
 *
 * ── Partial Updates ───────────────────────────────────────────────────────────
 * All fields are optional (sometimes) — the controller uses array_filter on
 * the validated data so only sent fields are updated. This supports PATCH
 * semantics: send only what changed.
 *
 * ── Uniqueness Scoping ────────────────────────────────────────────────────────
 * name and room uniqueness rules ignore the current section record so an admin
 * can save without changing those fields (i.e., unique check ignores self).
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Authorization handled in controller via $this->authorize('update', $section).
 */
class UpdateClassSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The section being updated — from route model binding
        $section  = $this->route('classSection') ?? $this->route('section');
        $schoolId = GetSchoolModel()?->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('class_sections', 'name')
                    ->where('school_id', $schoolId)
                    ->where('class_level_id', $section?->class_level_id)
                    ->whereNull('deleted_at')
                    ->ignore($section?->id), // Don't flag the current record as duplicate
            ],

            'display_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'room' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('class_sections', 'room')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->ignore($section?->id),
            ],

            'capacity' => [
                'sometimes',
                'integer',
                'min:0',
                'max:1000',
            ],

            'form_teacher_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('staff', 'id')->where('school_id', $schoolId),
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:9999',
            ],

            'status' => [
                'sometimes',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'           => 'A section with this name already exists in this class level.',
            'room.unique'           => 'This room is already assigned to another section in your school.',
            'capacity.min'          => 'Capacity must be 0 (uncapped) or a positive number.',
            'capacity.max'          => 'Capacity cannot exceed 1,000 students.',
            'form_teacher_id.exists'=> 'The selected form teacher was not found.',
            'status.in'             => 'Status must be either active or inactive.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'            => 'section name',
            'display_name'    => 'display name',
            'room'            => 'room',
            'capacity'        => 'capacity',
            'form_teacher_id' => 'form teacher',
            'sort_order'      => 'sort order',
            'status'          => 'status',
        ];
    }
}
