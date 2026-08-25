<?php

namespace App\Http\Requests\Student;

use App\Models\Academic\Student;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateStudentRequest – Validation for Updating an Existing Student Record (v2.0 – Production-Ready)
 *
 * This Form Request handles PATCH updates to a Student model.
 * It allows partial updates (only changed fields are validated) while enforcing
 * strict rules on critical fields like admission_number, status, and placement-related data.
 *
 * Features / Problems Solved:
 * - Partial update support using 'sometimes' rules.
 * - Prevents changing immutable fields (profile_id, school_id) after creation.
 * - Uses InDynamicEnum for school-customizable fields (status, admission_type).
 * - Validates admission_number uniqueness per school.
 * - Clear, context-aware error messages for admin/teacher interface.
 * - Prepares validated data for StudentController@update and related services.
 *
 * Fits into the Student Management Module:
 * - Used by StudentController@update.
 * - Called from frontend: Student/Show.vue edit form or inline editing in DataTable.
 * - Works with Student model (HasDynamicEnum for status/admission_type) and StudentStatusService.
 * - Ensures data integrity when updating enrollment details, status, or notes.
 */

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Route middleware usually handles permission: students.update
        // Additional check: ensure the student belongs to current school (via BelongsToSchool)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            // ── Core Enrollment Fields (sometimes = partial update) ─────────────
            'admission_number' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('students', 'admission_number')
                    ->ignore($student->id)
                    ->where('school_id', $student->school_id),
            ],

            'admission_date' => 'sometimes|date',

            'admission_type' => [
                'sometimes',
                'string',
                'max:30',
                new InDynamicEnum('admission_type', Student::class),
            ],

            // ── Status (highly sensitive – controlled via StudentStatusService in most cases) ──
            'status' => [
                'sometimes',
                'string',
                'max:50',
                new InDynamicEnum('status', Student::class),
            ],

            // Status change metadata (usually set by service, but allowed here for direct updates)
            'status_reason' => 'sometimes|nullable|string|max:1000',
            'status_date' => 'sometimes|nullable|date',
            'status_until' => 'sometimes|nullable|date|after:status_date',

            // ── Transfer / Previous School Fields ─────────────────────────────
            'previous_school' => 'sometimes|nullable|string|max:255',
            'previous_class' => 'sometimes|nullable|string|max:100',
            'previous_school_address' => 'sometimes|nullable|string|max:500',
            'transfer_destination' => 'sometimes|nullable|string|max:255',
            'transfer_certificate_number' => 'sometimes|nullable|string|max:100',

            // ── Notes (always allowed for updates)
            'notes' => 'sometimes|nullable|string|max:2000',

            // ── Custom Fields (from HasCustomFields trait)
            'custom_data' => 'sometimes|nullable|array',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'admission_number.unique' => 'This admission number is already in use for this school.',
            'status.in' => 'The selected status is not valid for this school.',
            'admission_type.in' => 'The selected admission type is not valid for this school.',
            'status_until.after' => 'The "status until" date must be after the status date.',
        ];
    }

    /**
     * Prepare validated data for the controller/service layer
     * (removes fields that should never be directly updated)
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        // Never allow changing these core immutable fields via this request
        unset($data['profile_id'], $data['school_id']);

        return $data;
    }

    /**
     * Additional validation after basic rules
     */
    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $student = $this->route('student');

            // Prevent changing status to 'transferred' or 'graduated' directly here
            // These should go through dedicated status services for proper side effects
            if ($this->filled('status')) {
                $restrictedStatuses = ['transferred', 'graduated', 'deceased'];

                if (in_array($this->input('status'), $restrictedStatuses)) {
                    $validator->errors()->add(
                        'status',
                        "Status '{$this->input('status')}' cannot be set directly. Use the dedicated status actions."
                    );
                }
            }

            // If changing admission_number, ensure it's not empty
            if ($this->filled('admission_number') && empty(trim($this->input('admission_number')))) {
                $validator->errors()->add('admission_number', 'Admission number cannot be empty.');
            }
        });
    }
}
