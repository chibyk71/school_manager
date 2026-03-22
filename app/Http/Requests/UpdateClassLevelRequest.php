<?php

/**
 * UpdateClassLevelRequest
 *
 * Validates incoming data when updating an existing ClassLevel.
 *
 * Key differences from StoreClassLevelRequest:
 * ─────────────────────────────────────────────────────────────────────────────
 * - All fields are 'sometimes' — partial updates are supported. An admin editing
 *   only the display_name should not need to re-send name and sequence.
 *
 * - Unique rules ignore the current record using ->ignore($this->classLevel->id)
 *   so the level can be saved with its existing name/sequence without triggering
 *   a false uniqueness conflict.
 *
 * - school_section_id is NOT updatable after creation. Changing the section a
 *   level belongs to would invalidate student assignments, timetables, and
 *   promotion paths. It is stripped in prepareForValidation if sent.
 *   The section always comes from the route parameter.
 *
 * - is_active change is guarded at the SERVICE layer (not here) — if students
 *   are currently enrolled, the service will reject deactivation with a clear
 *   error. The request just validates the data type.
 *
 * - sequence update is allowed but the service will handle reordering conflicts
 *   (e.g. shifting other levels if needed). The request just validates uniqueness
 *   ignoring the current record.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via permitted() in controller
    }

    protected function prepareForValidation(): void
    {
        // section_id is immutable after creation — always use the route value.
        // Strip any school_section_id from the body to prevent override attempts.
        $this->request->remove('school_section_id');
        $this->request->remove('school_id');
    }

    public function rules(): array
    {
        // Resolve the class level from the route.
        // Route parameter name must match: /class-levels/{classLevel}
        $classLevel = $this->route('classLevel');
        $sectionId  = $classLevel->school_section_id;

        return [
            // ── Name ──────────────────────────────────────────────────────
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('class_levels', 'name')
                    ->where('school_section_id', $sectionId)
                    ->whereNull('deleted_at')
                    ->ignore($classLevel->id),
            ],

            // ── Optional display fields ───────────────────────────────────
            'display_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'alias'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:500'],

            // ── Sequence ──────────────────────────────────────────────────
            'sequence' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('class_levels', 'sequence')
                    ->where('school_section_id', $sectionId)
                    ->whereNull('deleted_at')
                    ->ignore($classLevel->id),
            ],

            // ── Capacity ──────────────────────────────────────────────────
            'max_arms' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],

            // ── Status ────────────────────────────────────────────────────
            // Actual guard (cannot deactivate if students enrolled) is in the service.
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'     => 'A class level with this name already exists in this section.',
            'sequence.unique' => 'Another class level already occupies this sequence position in this section.',
            'sequence.min'    => 'Sequence must be at least 1.',
            'max_arms.min'    => 'Maximum arms must be at least 1 if specified.',
        ];
    }

    public function attributes(): array
    {
        return [
            'display_name' => 'display name',
            'max_arms'     => 'maximum arms',
            'is_active'    => 'active status',
        ];
    }
}
