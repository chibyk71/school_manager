<?php

namespace App\Http\Requests\Student;

use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PlaceStudentRequest – Validation for Student Placement / Class Assignment (v2.0 – Production-Ready)
 *
 * This Form Request validates data when assigning or changing a student's academic placement
 * (class level + optional section/arm) for a specific academic session.
 *
 * It is used for:
 *   - Initial placement during enrollment
 *   - Mid-session class/section changes (arm moves)
 *   - Manual placement updates via Student Placement modal
 *
 * Features / Problems Solved:
 * - Strict validation that ensures a valid academic session and class level.
 * - class_section_id is optional (supports schools that assign arms later).
 * - Uses InDynamicEnum for promotion_outcome (school-customizable).
 * - Prevents placing a student into the same session twice (handled via service, validated here).
 * - Clear, actionable error messages for teachers/admins.
 * - Prepares clean data for StudentPlacementService::placeInSession().
 *
 * Fits into the Student Management Module:
 * - Used by StudentPlacementController@store and @update.
 * - Called from frontend: Enrollment Wizard (Step 4), Placement modal, Student Show → Academic tab.
 * - Works seamlessly with StudentPlacementService and StudentSessionPlacement model.
 * - Integrates with HasDynamicEnum via InDynamicEnum rule.
 */

class PlaceStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usually protected by middleware (permission: students.place or similar)
        // Additional check can be added in controller if needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /** @var \App\Models\Academic\Student $student */
        $student = $this->route('student');

        return [
            // ── Required Placement Information ───────────────────────────────
            'academic_session_id' => [
                'required',
                'exists:academic_sessions,id',
            ],

            'class_level_id' => [
                'required',
                'exists:class_levels,id',
            ],

            'class_section_id' => [
                'nullable',
                'exists:class_sections,id',
            ],

            // ── Promotion / Placement Metadata ───────────────────────────────
            'promotion_outcome' => [
                'sometimes',
                'string',
                'max:50',
                new InDynamicEnum('promotion_outcome', \App\Models\Student\StudentSessionPlacement::class),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // ── Additional context (optional) ────────────────────────────────
            'is_current' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'academic_session_id.required' => 'Please select the academic session.',
            'class_level_id.required' => 'Please select the class level for this student.',
            'class_section_id.exists' => 'The selected class section does not exist.',
        ];
    }

    /**
     * Additional business validation after basic rules
     */
    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $student = $this->route('student');

            if (!$student) {
                $validator->errors()->add('student', 'Student not found.');
                return;
            }

            $sessionId = $this->input('academic_session_id');

            // Prevent duplicate placement in the same session
            $existing = \App\Models\Student\StudentSessionPlacement::where('student_id', $student->id)
                ->where('academic_session_id', $sessionId)
                ->exists();

            if ($existing && !$this->isMethod('PUT')) { // Allow updates to existing placement
                $validator->errors()->add(
                    'academic_session_id',
                    'This student already has a placement for the selected academic session.'
                );
            }

            // Optional: Prevent placing into a past session (can be disabled per school policy)
            // $session = AcademicSession::find($sessionId);
            // if ($session && $session->end_date < now()->toDateString()) {
            //     $validator->errors()->add('academic_session_id', 'Cannot place student into a past academic session.');
            // }
        });
    }

    /**
     * Return validated data formatted for StudentPlacementService::placeInSession()
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        return [
            'academic_session_id' => $data['academic_session_id'],
            'class_level_id' => $data['class_level_id'],
            'class_section_id' => $data['class_section_id'] ?? null,
            'promotion_outcome' => $data['promotion_outcome'] ?? 'manual_placement',
            'notes' => $data['notes'] ?? null,
            'is_current' => $data['is_current'] ?? true,
        ];
    }
}
