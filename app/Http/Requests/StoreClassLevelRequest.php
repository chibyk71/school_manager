<?php

/**
 * StoreClassLevelRequest
 *
 * Validates incoming data when creating a new ClassLevel.
 *
 * Key decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - school_section_id comes from the ROUTE (not the request body) because the
 *   endpoint is nested: POST /sections/{section}/class-levels. We pull it from
 *   the route parameter and merge it in prepareForValidation so it is always
 *   present and cannot be spoofed by sending a different section_id in the body.
 *
 * - Uniqueness is scoped to school_section_id only (no school_id column exists
 *   on class_levels). The old unique rule was wrong — it referenced school_id
 *   which does not exist on the table.
 *
 * - sequence uniqueness is validated here so the service layer receives already-
 *   validated data. The service still enforces it at DB level (unique constraint)
 *   but the request gives a user-friendly message before hitting the DB.
 *
 * - school_section_id ownership is verified: the section must belong to the
 *   current school. This prevents a user from posting to a valid section that
 *   belongs to a different tenant.
 *
 * - No school_id merging — that field does not exist on class_levels.
 *   Removed prepareForValidation school_id injection entirely.
 *
 * - max_arms is nullable but when present must be a positive integer.
 *
 * - is_active defaults to true in the model so it is optional here.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via permitted() in controller
    }

    protected function prepareForValidation(): void
    {
        // Pull section from route parameter — it is the authoritative source.
        // This prevents body-spoofing a different section_id.
        $this->merge([
            'school_section_id' => $this->route('section')?->id
                ?? $this->route('school_section_id'),
        ]);
    }

    public function rules(): array
    {
        $sectionId = $this->input('school_section_id');

        return [
            // ── Section ───────────────────────────────────────────────────
            // Must exist and must belong to the current school (ownership check).
            'school_section_id' => [
                'required',
                'uuid',
                Rule::exists('school_sections', 'id')->where(function ($query) {
                    $query->where('school_id', GetSchoolModel()?->id);
                }),
            ],

            // ── Name ──────────────────────────────────────────────────────
            // Required, unique within the section.
            // No school_id in the unique constraint — that column does not exist.
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('class_levels', 'name')
                    ->where('school_section_id', $sectionId)
                    ->whereNull('deleted_at'),
            ],

            // ── Optional display fields ───────────────────────────────────
            'display_name' => ['nullable', 'string', 'max:150'],
            'alias'        => ['nullable', 'string', 'max:20'],
            'description'  => ['nullable', 'string', 'max:500'],

            // ── Sequence ──────────────────────────────────────────────────
            // Required, must be a positive integer, unique within the section.
            // Drives promotion ordering — no two levels can occupy the same position.
            'sequence' => [
                'required',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('class_levels', 'sequence')
                    ->where('school_section_id', $sectionId)
                    ->whereNull('deleted_at'),
            ],

            // ── Capacity ──────────────────────────────────────────────────
            // Optional soft cap on number of streams under this level.
            'max_arms' => ['nullable', 'integer', 'min:1', 'max:50'],

            // ── Status ────────────────────────────────────────────────────
            'is_active' => ['boolean'],
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
            'school_section_id' => 'school section',
            'display_name'      => 'display name',
            'max_arms'          => 'maximum arms',
            'is_active'         => 'active status',
        ];
    }
}
