<?php

namespace App\Http\Requests\Academic;

use App\Models\Exam\AssessmentTemplate;
use App\Models\Exam\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * UpdateExamRequest
 *
 * Validates PATCH /exams/{exam}.
 *
 * Key differences from StoreExamRequest:
 * ─────────────────────────────────────────────────────────────────────────────
 * - All fields are optional (only send what changed)
 * - assessment_template_id cannot be changed if scores have been entered
 * - class_level_id and class_section_id cannot change once published
 * - The exam must still be editable (not locked) to accept any update
 *
 * Authorization: defers to ExamPolicy::update() via Gate::authorize in controller,
 * but also enforces the "must be editable" rule here for clear error messages.
 */
class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');
        return Gate::allows('update', $exam);
    }

    public function rules(): array
    {
        /** @var Exam $exam */
        $exam = $this->route('exam');

        $classId = $this->input('class_level_id', $exam->class_level_id);

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Session and term — always updatable while in draft
            'academic_session_id' => [
                'sometimes', 'required',
                Rule::exists('academic_sessions', 'id'),
            ],
            'term_id' => [
                'nullable',
                Rule::exists('terms', 'id'),
            ],

            // Class scope — locked once published
            'class_level_id' => [
                'sometimes',
                'nullable',
                Rule::exists('class_levels', 'id'),
                Rule::prohibitedIf($exam->isPublished() || !$exam->isDraft()),
            ],
            'class_section_id' => [
                'sometimes',
                'nullable',
                Rule::exists('class_sections', 'id'),
                Rule::prohibitedIf($exam->isPublished() || !$exam->isDraft()),
            ],

            // Template — cannot change once scores exist
            'assessment_template_id' => [
                'sometimes',
                'required',
                Rule::exists('assessment_templates', 'id'),
                function ($attribute, $value, $fail) use ($exam) {
                    if ($value === $exam->assessment_template_id) return; // no change
                    $hasScores = $exam->examResults()->exists();
                    if ($hasScores) {
                        $fail('The assessment template cannot be changed after scores have been entered. Delete all scores first or create a new exam.');
                    }
                    $template = AssessmentTemplate::find($value);
                    if ($template && !$template->is_active) {
                        $fail('The selected assessment template is inactive.');
                    }
                    if ($template && $template->school_id && $template->school_id !== $exam->school_id) {
                        $fail('The selected template does not belong to your school.');
                    }
                },
            ],

            // Dates
            'exam_start_date' => ['nullable', 'date'],
            'exam_end_date'   => ['nullable', 'date', 'after_or_equal:exam_start_date'],
        ];
    }

    /**
     * Ensure the exam is still in an editable state before processing.
     * This produces a clean validation error rather than a silent rejection.
     */
    public function withValidator($validator): void
    {
        /** @var Exam $exam */
        $exam = $this->route('exam');

        $validator->after(function ($v) use ($exam) {
            if (!$exam->isEditable()) {
                $v->errors()->add('status', 'This exam can no longer be edited because it has been locked or approved.');
            }

            // Validate class_section belongs to class_level
            $sectionId = $this->input('class_section_id');
            $levelId   = $this->input('class_level_id', $exam->class_level_id);
            if ($sectionId && $levelId) {
                $valid = \App\Models\Academic\ClassSection::where('id', $sectionId)
                    ->where('class_level_id', $levelId)
                    ->exists();
                if (!$valid) {
                    $v->errors()->add('class_section_id', 'The selected section does not belong to the chosen class level.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'class_level_id.prohibited'   => 'The class level cannot be changed after an exam is published.',
            'class_section_id.prohibited' => 'The class section cannot be changed after an exam is published.',
        ];
    }
}
