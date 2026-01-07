<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the **User Management** settings group.
 *
 * @group Settings
 */
class UserManagementSettingsRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        // `permitted()` is called in the controller
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            // ── Admission ────────────────────────────────────────
            'online_admission'               => ['required', 'boolean'],
            'online_admission_fee'           => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'online_admission_instruction'   => ['nullable', 'string', 'max:2000'],

            // ── Sign-in permissions ───────────────────────────────
            'allow_student_signin'           => ['required', 'boolean'],
            'allow_parent_signin'            => ['required', 'boolean'],
            'allow_teacher_signin'           => ['required', 'boolean'],
            'allow_staff_signin'             => ['required', 'boolean'],

            // ── Enrollment ID ─────────────────────────────────────
            'enrollment_id_format'           => ['required', 'string', 'max:255'],
            'enrollment_id_number_length'    => ['required', 'integer', 'min:4', 'max:20'],

            // ── Guardian rules ───────────────────────────────────
            'require_guardian_email'         => ['required', 'boolean'],
            'max_guardian_students'          => ['required', 'integer', 'min:1', 'max:50'],

            // ── Bulk & custom fields ───────────────────────────────
            'allow_bulk_user_creation'       => ['required', 'boolean']
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'online_admission'               => 'online admission',
            'online_admission_fee'           => 'online admission fee',
            'online_admission_instruction'   => 'admission instruction',
            'allow_student_signin'           => 'allow student sign-in',
            'allow_parent_signin'            => 'allow parent sign-in',
            'allow_teacher_signin'           => 'allow teacher sign-in',
            'allow_staff_signin'             => 'allow staff sign-in',
            'enrollment_id_format'           => 'enrollment ID format',
            'enrollment_id_number_length'    => 'enrollment ID number length',
            'require_guardian_email'         => 'require guardian email',
            'max_guardian_students'          => 'max students per guardian',
            'allow_bulk_user_creation'       => 'allow bulk user creation',
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'online_admission_fee.max'      => 'The admission fee cannot exceed ₦999,999.99.',
            'enrollment_id_number_length.max'=> 'The numeric part of the enrollment ID cannot be longer than 20 digits.',
            'max_guardian_students.max'     => 'A guardian can be linked to a maximum of 50 students.',
            'custom_field_requirements.*.fields.*.distinct' => 'Duplicate field names are not allowed for the same type.',
        ];
    }
}
