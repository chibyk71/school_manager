<?php

namespace App\Http\Requests\Student;

use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * EnrollStudentRequest – Validation for Multi-Step Student Enrollment Wizard (v2.0 – Production-Ready)
 *
 * This Form Request validates the entire payload submitted from the Enrollment Wizard
 * (typically 5–6 steps). It handles nested data for personal info, placement, guardians,
 * and optional portal access.
 *
 * Features / Problems Solved:
 * - Comprehensive validation across all wizard steps in a single request.
 * - Nested array validation for complex wizard data structure.
 * - Uses InDynamicEnum rule for school-customizable fields (status, admission_type, gender, etc.).
 * - Strict guardian validation (at least one guardian required for final enrollment).
 * - Clean separation of data per step for easy mapping in StudentEnrollmentService.
 * - User-friendly error messages that map back to specific wizard steps.
 * - Prepares validated data for StudentEnrollmentService::enrollFromWizard().
 *
 * Fits into the Student Management Module:
 * - Used by StudentController@store (when submitting the enrollment wizard).
 * - Consumed by the frontend EnrollmentWizard component (Step1SessionInfo → Step6Review).
 * - Works seamlessly with StudentEnrollmentService and existing traits (HasDynamicEnum, HasCustomFields).
 * - Ensures data integrity before any Student/Profile/Guardian records are created.
 */

class EnrollStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usually protected by middleware (auth + permission: students.create)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // ── Step 1: Session & Academic Intent ─────────────────────────────
            'placement.academic_session_id' => 'required|exists:academic_sessions,id',
            'placement.class_level_id'      => 'required|exists:class_levels,id',
            'placement.class_section_id'    => 'nullable|exists:class_sections,id',
            'placement.notes'               => 'nullable|string|max:500',

            // ── Step 2: Personal Information ──────────────────────────────────
            'personal.first_name'    => 'required|string|max:100',
            'personal.last_name'     => 'required|string|max:100',
            'personal.middle_name'   => 'nullable|string|max:100',
            'personal.date_of_birth' => 'required|date|before:today',
            'personal.gender'        => ['required', new InDynamicEnum('gender', \App\Models\Profile::class)],
            'personal.phone'         => 'nullable|string|max:30',
            'personal.email'         => 'nullable|email|max:191',
            'personal.nationality'   => 'nullable|string|max:100',
            'personal.state_of_origin'=> 'nullable|string|max:100',
            'personal.religion'      => ['nullable', new InDynamicEnum('religion', \App\Models\Profile::class)],
            'personal.blood_group'   => ['nullable', new InDynamicEnum('blood_group', \App\Models\Profile::class)],

            // ── Step 3: Guardians ─────────────────────────────────────────────
            'guardians'              => 'required|array|min:1',
            'guardians.*.personal.first_name' => 'required|string|max:100',
            'guardians.*.personal.last_name'  => 'required|string|max:100',
            'guardians.*.personal.phone'      => 'required|string|max:30',
            'guardians.*.personal.email'      => 'nullable|email|max:191',
            'guardians.*.relationship'        => 'required|string|max:50',
            'guardians.*.is_primary_contact'  => 'boolean',
            'guardians.*.can_pickup'          => 'boolean',
            'guardians.*.can_access_portal'   => 'boolean',
            'guardians.*.is_emergency_contact'=> 'boolean',
            'guardians.*.emergency_contact_priority' => 'nullable|integer|min:1|max:5',

            // ── Step 4: Admission Details ─────────────────────────────────────
            'enrollment.admission_number' => 'nullable|string|max:50|unique:students,admission_number,NULL,id,school_id,' . $this->user()?->school_id,
            'enrollment.admission_type'   => ['required', new InDynamicEnum('admission_type', \App\Models\Student\Student::class)],
            'enrollment.notes'            => 'nullable|string|max:1000',

            // ── Step 5: Portal Access (Optional) ──────────────────────────────
            'portal_access.create_account' => 'boolean',
            'portal_access.username'       => 'required_if:portal_access.create_account,true|string|max:50',
            'portal_access.password'       => 'required_if:portal_access.create_account,true|string|min:8',

            // ── Custom Fields (from HasCustomFields) ──────────────────────────
            'custom_data' => 'nullable|array',
        ];
    }

    /**
     * Custom validation messages for better UX in the wizard
     */
    public function messages(): array
    {
        return [
            'personal.first_name.required'                  => 'Student first name is required.',
            'personal.last_name.required'                   => 'Student last name is required.',
            'personal.date_of_birth.required'               => 'Date of birth is required.',
            'personal.date_of_birth.before'                 => 'Date of birth must be in the past.',
            'guardians.required'                            => 'At least one guardian is required.',
            'guardians.min'                                 => 'At least one guardian must be provided.',
            'guardians.*.personal.first_name.required'      => 'Guardian first name is required.',
            'guardians.*.personal.phone.required'           => 'Guardian phone number is required.',
            'placement.academic_session_id.required'        => 'Please select the academic session.',
            'placement.class_level_id.required'             => 'Please select the class level.',
            'portal_access.username.required_if'            => 'Username is required when creating a portal account.',
            'portal_access.password.required_if'            => 'Password is required when creating a portal account.',
        ];
    }

    /**
     * Prepare data before validation (normalize structure)
     */
    protected function prepareForValidation(): void
    {
        // Ensure guardians is always an array
        if ($this->has('guardians') && !is_array($this->guardians)) {
            $this->merge(['guardians' => []]);
        }

        // Default portal_access structure
        if (!$this->has('portal_access')) {
            $this->merge(['portal_access' => ['create_account' => false]]);
        }
    }

    /**
     * Return validated data formatted for StudentEnrollmentService::enrollFromWizard()
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        return [
            'personal'     => $data['personal'] ?? [],
            'placement'    => $data['placement'] ?? [],
            'guardians'    => $data['guardians'] ?? [],
            'enrollment'   => $data['enrollment'] ?? [],
            'portal_access'=> $data['portal_access'] ?? ['create_account' => false],
            'custom_data'  => $data['custom_data'] ?? [],
        ];
    }
}