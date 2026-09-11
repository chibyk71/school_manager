<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use App\States\Academic\AcademicSession\Active as SessionActive;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UpdateAcademicSessionRequest – Validation for Updating an Existing Academic Session
 *
 * Phase 1: is_current is not accepted. Start-date immutability uses authoritative ACTIVE state.
 */
class UpdateAcademicSessionRequest extends FormRequest
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

        $session = $this->route('academicSession');
        if (! $session instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'session' => 'Invalid academic session route parameter.',
            ]);
        }

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique(AcademicSession::class, 'name')
                    ->where('school_id', $school->id)
                    ->ignore($session->id),
            ],
            'start_date' => [
                'sometimes',
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
                function ($attribute, $value, $fail) use ($session) {
                    $isActive = $session->state instanceof SessionActive
                        || (string) $session->state === SessionActive::$name;
                    if ($isActive && Carbon::parse($value)->notEqualTo($session->start_date)) {
                        $fail('The start date of an active or previously activated session cannot be changed.');
                    }
                },
            ],
            'end_date' => [
                'sometimes',
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

        if ($school && ! $this->has('school_id')) {
            $this->merge([
                'school_id' => $school->id,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The session name is required (e.g., 2025/2026).',
            'name.unique' => 'A session with this name already exists for your school.',
            'start_date.required' => 'The start date is required.',
            'end_date.required' => 'The end date is required.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
