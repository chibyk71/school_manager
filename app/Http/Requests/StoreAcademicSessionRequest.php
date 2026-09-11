<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * StoreAcademicSessionRequest – Validation for Creating a New Academic Session
 *
 * Phase 1: is_current is not a lifecycle field. Lifecycle transitions belong to
 * AcademicCalendarService / Spatie state machinery, not request input.
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
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
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
            'start_date.required' => 'The start date is required.',
            'end_date.required'   => 'The end date is required.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal'    => 'The end date must be on or after the start date.',
        ];
    }
}
