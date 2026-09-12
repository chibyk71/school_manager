<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UpdateAcademicSessionRequest – Syntactic validation only (Phase 2).
 *
 * Domain date rules (ordering, overlap, operational-data start-date immutability)
 * live in AcademicSessionLifecycleService::updateDates(). This request does not
 * hard-code ACTIVE-state immutability.
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
            // Syntactic only — domain rules enforced in lifecycle service
            'start_date' => [
                'sometimes',
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'sometimes',
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'state' => ['prohibited'],
            'is_current' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school && ! $this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The session name is required.',
            'name.unique'   => 'A session with this name already exists for your school.',
            'state.prohibited' => 'Lifecycle state cannot be set via this endpoint.',
        ];
    }
}
