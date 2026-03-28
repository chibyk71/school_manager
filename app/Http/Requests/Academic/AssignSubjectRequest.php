<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AssignSubjectRequest — validates adding a teacher-subject assignment to a section.
 *
 * Used by ClassSectionController::assignSubject().
 * The class_section_id comes from the route — not validated here.
 * Duplicate assignment check is enforced by the service (not here) because
 * it requires the section context and produces a richer error message.
 */
class AssignSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = GetSchoolModel()?->id;

        return [
            'teacher_id' => [
                'required',
                'uuid',
                Rule::exists('staff', 'id')->where('school_id', $schoolId),
            ],

            'subject_id' => [
                'required',
                'uuid',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],

            // Optional role — null means default subject teacher
            'role' => [
                'nullable',
                'string',
                'max:50',
                // Soft validation — allow any string but recommend known values
                // Hard enum is deliberately avoided to allow custom school roles
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required' => 'A teacher must be selected.',
            'teacher_id.exists'   => 'The selected teacher was not found in your school.',
            'subject_id.required' => 'A subject must be selected.',
            'subject_id.exists'   => 'The selected subject was not found in your school.',
            'role.max'            => 'Role must not exceed 50 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'teacher_id' => 'teacher',
            'subject_id' => 'subject',
            'role'       => 'role',
        ];
    }
}
