<?php

namespace App\Http\Requests;

use App\Models\Academic\Subject;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateSubjectRequest – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Validates all incoming data when updating an existing Subject. Uses 'sometimes'
 * for most fields to allow partial updates from the modal form without sending
 * all fields on every save.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • Subject code uniqueness scoped to school, ignoring the subject being updated
 * • Partial update safe: 'sometimes' on non-required fields
 * • All same relationship validation as Store request
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FITS INTO THE MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * • Used by SubjectController::update()
 * • Validated data passed directly to SubjectService::update()
 */
class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $school    = GetSchoolModel();
        $subjectId = $this->route('subject')?->id ?? $this->route('subject');

        return [
            'name' => 'sometimes|required|string|max:150',

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'alpha_num',
                Rule::unique('subjects', 'code')
                    ->where('school_id', $school?->id)
                    ->whereNull('deleted_at')
                    ->ignore($subjectId),
            ],

            'description'  => 'nullable|string|max:1000',
            'type'         => ['sometimes', 'required', 'string', new InDynamicEnum('subject_type', Subject::class)],
            'category'     => ['sometimes', 'required', 'string', new InDynamicEnum('subject_category', Subject::class)],
            'is_active'    => 'sometimes|boolean',
            'pass_mark'    => 'nullable|integer|min:0|max:100',
            'credit_hours' => 'nullable|integer|min:1|max:40',
            'sort'         => 'nullable|integer|min:0',
            'color'        => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],

            'school_section_ids'   => 'nullable|array',
            'school_section_ids.*' => 'exists:school_sections,id',

            'class_level_ids'      => 'nullable|array',
            'class_level_ids.*'    => 'exists:class_levels,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'    => 'A subject with this code already exists in your school.',
            'code.alpha_num' => 'Subject code must contain only letters and numbers (e.g. MTH, ENG01).',
            'type.in'        => 'Subject type must be one of: core, elective, optional.',
            'category.in'    => 'Subject category must be one of: sciences, arts, commerce, languages, technical, general.',
            'pass_mark.max'  => 'Pass mark cannot exceed 100.',
            'color.regex'    => 'Color must be a valid hex code (e.g. #3B82F6).',
        ];
    }
}
