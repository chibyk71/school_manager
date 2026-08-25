<?php

namespace App\Http\Requests\Student;

use App\Models\Academic\StudentApplication;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AdmitApplicationRequest – Validation for Admitting a Student Application (v2.0 – Production-Ready)
 *
 * This Form Request validates data when an admin admits a pending StudentApplication.
 * It ensures that the application is in a valid state and that all required placement
 * and enrollment details are provided before creating the Profile + Student record.
 *
 * Features / Problems Solved:
 * - Validates that the application exists and is in 'pending' status.
 * - Enforces required placement information (session, class level).
 * - Supports optional class section (arm) assignment.
 * - Uses InDynamicEnum rule for school-customizable fields where applicable.
 * - Provides clear, user-friendly validation messages for the admin interface.
 * - Prepares validated data for StudentApplicationService::admitApplication().
 *
 * Fits into the Student Management Module:
 * - Used by ApplicationController@admit method.
 * - Called after admin clicks "Admit" in Applications/Show.vue or Applications/Index.vue actions.
 * - Works seamlessly with StudentApplicationService and StudentEnrollmentService.
 * - Integrates with HasDynamicEnum (via InDynamicEnum rule) and existing traits.
 */

class AdmitApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is typically handled by route middleware (e.g., permission: applications.admit)
        // We can add extra checks here if needed (e.g., application belongs to current school)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $application = $this->route('application'); // StudentApplication model from route model binding

        return [
            // Application must exist and be pending
            'application' => [
                'required',
                Rule::exists('student_applications', 'id')
                    ->where('id', $application?->id)
                    ->where('status', 'pending'),
            ],

            // Required placement information for admission
            'placement.academic_session_id' => 'required|exists:academic_sessions,id',
            'placement.class_level_id' => 'required|exists:class_levels,id',
            'placement.class_section_id' => 'nullable|exists:class_sections,id',

            // Optional notes for the new student record
            'notes' => 'nullable|string|max:1000',

            // Optional custom data to be passed to the new Student record
            'custom_data' => 'nullable|array',

            // Status override (rarely used – usually defaults to 'admitted')
            'initial_status' => [
                'nullable',
                Rule::in(['admitted', 'enrolled']),
            ],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'placement.academic_session_id.required' => 'Please select the academic session for enrollment.',
            'placement.class_level_id.required' => 'Please select the class level for the student.',
            'application.exists' => 'This application cannot be admitted (it may have already been processed or does not exist).',
        ];
    }

    /**
     * Additional after-validation logic
     */
    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $application = $this->route('application');

            if (!$application) {
                $validator->errors()->add('application', 'Application not found.');
                return;
            }

            if ($application->status !== 'pending') {
                $validator->errors()->add('application', 'Only pending applications can be admitted.');
            }

            // Optional: Add more business rules here (e.g., check if desired class level is available)
        });
    }

    /**
     * Get the validated data formatted for the service layer
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        return [
            'placement' => [
                'academic_session_id' => $data['placement']['academic_session_id'],
                'class_level_id' => $data['placement']['class_level_id'],
                'class_section_id' => $data['placement']['class_section_id'] ?? null,
            ],
            'notes' => $data['notes'] ?? null,
            'custom_data' => $data['custom_data'] ?? [],
            'initial_status' => $data['initial_status'] ?? 'admitted',
        ];
    }
}