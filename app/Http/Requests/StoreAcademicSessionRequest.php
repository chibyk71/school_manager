<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * StoreAcademicSessionRequest – Validation for Creating a New Academic Session
 *
 * Phase 2: DRAFT may be incomplete — start_date / end_date are optional at creation.
 * Planning and activation enforce complete valid dates in the lifecycle service.
 * Lifecycle state is never accepted from user input (always defaults to DRAFT).
 */
class StoreAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $school = GetSchoolModel();

        if (! $school) {
            throw ValidationException::withMessages([
                'school' => 'No active school context found. Please select a school first.',
            ]);
        }

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(AcademicSession::class, 'name')
                    ->where('school_id', $school->id),
            ],
            // Phase 2: incomplete DRAFT is allowed — dates optional at creation
            'start_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            // Explicitly reject lifecycle state / flags from request body
            'state' => ['prohibited'],
            'is_current' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school) {
            $this->merge([
                'school_id' => $school->id,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The session name is required (e.g., 2025/2026).',
            'name.unique'   => 'A session with this name already exists for your school.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal'    => 'The end date must be on or after the start date.',
            'state.prohibited' => 'Lifecycle state cannot be set via this endpoint.',
        ];
    }
}
