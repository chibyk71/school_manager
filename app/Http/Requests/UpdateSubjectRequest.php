<?php

namespace App\Http\Requests;

use App\Models\Academic\Subject;
use App\Rules\InDynamicEnum;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateSubjectRequest – v1.0
 *
 * Validates subject updates. Type and category use Dynamic Enum keys
 * academic.subject_type and academic.subject_category with explicit school context.
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
            'type'         => ['sometimes', 'required', 'string', new InDynamicEnum('academic.subject_type', GetSchoolModel())],
            'category'     => ['sometimes', 'required', 'string', new InDynamicEnum('academic.subject_category', GetSchoolModel())],
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

    protected function prepareForValidation(): void
    {
        $payload = [];
        foreach (['type', 'category'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $payload[$field] = DynamicEnumValue::canonicalize($this->input($field));
            }
        }
        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
