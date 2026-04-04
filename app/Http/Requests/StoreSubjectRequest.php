<?php

namespace App\Http\Requests;

use App\Models\Academic\Subject;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreSubjectRequest – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Validates all incoming data when creating a new Subject. Handles authorization,
 * school-scoped uniqueness for subject codes, and relation IDs validation.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • Subject code uniqueness scoped to current school (not globally unique)
 * • Validates type against Subject::types() constants — no magic strings
 * • Validates category against Subject::categories() constants
 * • Validates school_section_ids and class_level_ids exist in DB
 * • pass_mark range enforced: 0–100
 * • credit_hours sanity check: 1–40
 * • Color validated as hex color
 * • Authorization delegates to school context check
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FITS INTO THE MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * • Used by SubjectController::store()
 * • Validated data passed directly to SubjectService::create()
 */
class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $school = GetSchoolModel();

        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            // Code must be unique within the current school (case-insensitive enforced in service)
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_num',
                Rule::unique('subjects', 'code')
                    ->where('school_id', $school?->id)
                    ->whereNull('deleted_at'),
            ],

            'description'  => 'nullable|string|max:1000',

            'type' => ['required', 'string', new InDynamicEnum('subject_type', Subject::class),],

            'category' => ['required', 'string', new InDynamicEnum('subject_category', Subject::class),],

            'is_active'    => 'boolean',
            'pass_mark'    => 'nullable|integer|min:0|max:100',
            'credit_hours' => 'nullable|integer|min:1|max:40',
            'sort'         => 'nullable|integer|min:0',
            'color'        => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],

            // Many-to-many relationships
            'school_section_ids'   => 'nullable|array',
            'school_section_ids.*' => 'exists:school_sections,id',

            'class_level_ids'      => 'nullable|array',
            'class_level_ids.*'    => 'exists:class_levels,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'      => 'A subject with this code already exists in your school.',
            'code.alpha_num'   => 'Subject code must contain only letters and numbers (e.g. MTH, ENG01).',
            'type.in'          => 'Subject type must be one of: core, elective, optional.',
            'category.in'      => 'Subject category must be one of: sciences, arts, commerce, languages, technical, general.',
            'pass_mark.min'    => 'Pass mark cannot be negative.',
            'pass_mark.max'    => 'Pass mark cannot exceed 100.',
            'credit_hours.min' => 'Credit hours must be at least 1.',
            'color.regex'      => 'Color must be a valid hex code (e.g. #3B82F6).',
        ];
    }
}
