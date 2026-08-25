<?php

namespace App\Http\Requests\Student;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TransferStudentRequest – Validation for Student Transfer Operations (v2.0 – Production-Ready)
 *
 * This Form Request validates data when transferring a student either:
 *   1. Internally within the tenant (to another school)
 *   2. Externally (leaving the tenant entirely)
 *
 * It enforces strict business rules:
 *   - Only active/enrolled students can be transferred
 *   - Internal transfers require a valid target school (different from current)
 *   - External transfers require destination details
 *   - Reason is mandatory for audit/compliance
 *
 * Features / Problems Solved:
 * - Clear distinction between internal and external transfers.
 * - Prevents invalid transfers (same school, inactive student, etc.).
 * - Uses route model binding for the source student.
 * - Prepares clean, validated data for StudentTransferService.
 * - User-friendly error messages suitable for admin interface.
 *
 * Fits into the Student Management Module:
 * - Used by StudentTransferController and StudentStatusController (when status = transferred).
 * - Called from frontend: Student Show page → Transfer action modal.
 * - Works seamlessly with StudentTransferService::transferWithinTenant() and transferOut().
 * - Aligns with your core rule: "Transfer = old student record marked transferred + new student record created".
 */

class TransferStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Usually protected by middleware (permission: students.transfer)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /** @var \App\Models\Academic\Student $student */
        $student = $this->route('student');

        $rules = [
            // Common required fields
            'reason' => 'required|string|max:1000',

            // Transfer type (internal or external)
            'transfer_type' => ['required', Rule::in(['internal', 'external'])],

            // ── Internal Transfer (within tenant) ─────────────────────────────
            'target_school_id' => [
                'required_if:transfer_type,internal',
                'exists:schools,id',
                Rule::notIn([$student?->school_id]), // Cannot transfer to same school
            ],

            // ── External Transfer ─────────────────────────────────────────────
            'destination' => [
                'required_if:transfer_type,external',
                'string',
                'max:255',
            ],

            // Additional context
            'notes' => 'nullable|string|max:1000',

            // Optional previous school address (for documentation)
            'previous_school_address' => 'nullable|string|max:500',
        ];

        return $rules;
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'reason.required'               => 'Please provide a reason for the transfer.',
            'transfer_type.required'        => 'Please specify the type of transfer (internal or external).',
            'target_school_id.required_if'  => 'Please select the target school for internal transfer.',
            'target_school_id.not_in'       => 'Cannot transfer to the same school.',
            'destination.required_if'       => 'Please specify the destination school for external transfer.',
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

            // Only allow transfer if student is in transferable status
            if (!$student->canTransfer()) {
                $validator->errors()->add(
                    'status',
                    "Only students with status 'active' or 'enrolled' can be transferred. Current status: {$student->status}"
                );
            }

            // Internal transfer: ensure target school is different and exists in tenant
            if ($this->input('transfer_type') === 'internal') {
                $targetSchoolId = $this->input('target_school_id');

                if ($targetSchoolId == $student->school_id) {
                    $validator->errors()->add('target_school_id', 'Target school must be different from current school.');
                }
            }
        });
    }

    /**
     * Return validated data formatted for StudentTransferService
     */
    public function validatedData(): array
    {
        $data = $this->validated();

        return [
            'transfer_type'           => $data['transfer_type'],
            'target_school_id'        => $data['target_school_id'] ?? null,
            'destination'             => $data['destination'] ?? null,
            'reason'                  => $data['reason'],
            'notes'                   => $data['notes'] ?? null,
            'previous_school_address' => $data['previous_school_address'] ?? null,
        ];
    }
}
