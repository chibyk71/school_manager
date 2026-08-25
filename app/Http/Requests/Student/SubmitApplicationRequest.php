<?php

namespace App\Http\Requests\Student;

use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SubmitApplicationRequest – Validation for Student Application Submission (v2.0 – Production-Ready)
 *
 * This Form Request validates data for both:
 *   - Public portal submissions (/apply/{slug})
 *   - Admin direct application creation
 *
 * It enforces strict rules on personal information, academic intent, and guardian data
 * while allowing flexibility for school-specific custom fields via JSON.
 *
 * Features / Problems Solved:
 * - Centralized, reusable validation for all application submission paths.
 * - Strong validation on personal data (names, DOB, contact) to reduce bad data.
 * - Conditional validation based on source (public vs admin).
 * - Proper handling of JSON fields (guardians_data, documents, custom_data).
 * - Nigeria-friendly rules: phone format, state_of_origin, blood_group, religion (via DynamicEnum later).
 * - Prepares data for StudentApplicationService (clean, validated array).
 * - Clear, user-friendly error messages for both public form and admin interface.
 *
 * Fits into the Student Management Module:
 * - Used by PublicApplicationController@store and ApplicationController@store.
 * - Feeds directly into StudentApplicationService::submitPublicApplication() and submitAdminApplication().
 * - Works with frontend: PublicApplicationForm.vue and admin application creation modal.
 * - Integrates with HasDynamicEnum (gender, religion will be validated against allowed options in future).
 */

class SubmitApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public portal is always allowed.
        // Admin creation is protected by route middleware (auth + permission).
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            // ── Academic Intent ─────────────────────────────────────────────
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'school_section_id' => 'nullable|exists:school_sections,id',
            'class_level_id' => 'nullable|exists:class_levels,id',

            // ── Applicant Personal Information ──────────────────────────────
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => ['nullable', 'string', 'max:30', new InDynamicEnum('gender', \App\Models\Profile::class)],           // Will be validated via DynamicEnum in service
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
            'nationality' => 'nullable|string|max:100',
            'state_of_origin' => 'nullable|string|max:100',
            'religion' => ['nullable|string|max:50', new InDynamicEnum('religion', \App\Models\Profile::class)],
            'blood_group' => 'nullable|string|max:10',

            // ── Previous School (for transfer applicants) ───────────────────
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'previous_school_address' => 'nullable|string|max:500',

            // ── Guardian Data (JSON array) ──────────────────────────────────
            'guardians_data' => 'nullable|array',
            'guardians_data.*.name' => 'required|string|max:150',
            'guardians_data.*.phone' => 'required|string|max:30',
            'guardians_data.*.email' => 'nullable|email|max:191',
            'guardians_data.*.relationship' => 'required|string|max:50',
            'guardians_data.*.is_primary' => 'boolean',

            // ── Supporting Documents ────────────────────────────────────────
            'documents' => 'nullable|array',
            'documents.*.type' => 'string|max:100',
            'documents.*.path' => 'string',

            // ── School-Specific Custom Fields ───────────────────────────────
            'custom_data' => 'nullable|array',

            // ── Source & Metadata ───────────────────────────────────────────
            'source' => ['sometimes', Rule::in(['public_portal', 'admin_direct'])],
        ];

        // Additional rules for admin-created applications
        if ($this->input('source') === 'admin_direct') {
            $rules['status'] = ['sometimes', Rule::in(['pending', 'admitted'])];
            $rules['reviewed_by'] = 'nullable|exists:users,id';
        }

        return $rules;
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Student first name is required.',
            'last_name.required' => 'Student last name is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'academic_session_id.required' => 'Please select the academic session.',
            'guardians_data.*.name.required' => 'Guardian name is required.',
            'guardians_data.*.phone.required' => 'Guardian phone number is required.',
        ];
    }

    /**
     * Prepare the data for validation / service layer
     */
    protected function prepareForValidation(): void
    {
        // Ensure guardians_data is always an array
        if ($this->has('guardians_data') && !is_array($this->guardians_data)) {
            $this->merge([
                'guardians_data' => json_decode($this->guardians_data, true) ?? [],
            ]);
        }

        // Default source for public portal
        if (!$this->has('source')) {
            $this->merge(['source' => 'public_portal']);
        }
    }

    /**
     * Get validated data ready for the service layer
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        // Ensure submitted_at is set for public applications
        if ($data['source'] === 'public_portal' && empty($data['submitted_at'])) {
            $data['submitted_at'] = now();
        }

        return $data;
    }
}
