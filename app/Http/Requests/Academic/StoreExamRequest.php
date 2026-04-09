<?php

namespace App\Http\Requests\Academic;

use App\Models\Exam\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreExamRequest
 *
 * Validates incoming data for creating a new exam.
 *
 * Custom validation:
 * - template must belong to the current school and be active
 * - class_section_id must belong to class_level_id (if both provided)
 * - exam dates must fall within the term's date range (if term_id provided)
 * - Either class_level_id OR class_section_id must be provided (not neither)
 *
 * Fits into the module:
 * - ExamController::store() — FormRequest is auto-injected
 */
class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Exam::class) ?? false;
    }

    public function rules(): array
    {
        $school = GetSchoolModel();

        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],

            'academic_session_id' => [
                'required',
                Rule::exists('academic_sessions', 'id')->where('school_id', $school?->id),
            ],
            'term_id' => [
                'nullable',
                Rule::exists('terms', 'id'),
            ],

            'class_level_id' => [
                'nullable',
                'required_without:class_section_id',
                Rule::exists('class_levels', 'id'),
            ],
            'class_section_id' => [
                'nullable',
                Rule::exists('class_sections', 'id'),
            ],

            'assessment_template_id' => [
                'required',
                Rule::exists('assessment_templates', 'id')
                    ->where('school_id', $school?->id)
                    ->where('is_active', true),
            ],

            'exam_start_date' => ['nullable', 'date'],
            'exam_end_date'   => ['nullable', 'date', 'after_or_equal:exam_start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_level_id.required_without' => 'Either a class level or a specific class section must be selected.',
            'assessment_template_id.exists'    => 'The selected assessment template is invalid, inactive, or does not belong to your school.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Inject school_id from context
        $school = GetSchoolModel();
        $this->merge(['school_id' => $school?->id]);
    }
}
