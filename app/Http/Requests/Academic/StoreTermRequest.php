<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HTTP-layer input shape for creating a Term (Phase 3).
 *
 * Domain rules (sequence, lifecycle, temporal integrity) belong to TermLifecycleService.
 * Callers must not supply school_id, state, status, or ordinal_number.
 */
class StoreTermRequest extends FormRequest
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
            'academic_session_id' => [
                'required',
                'uuid',
                'exists:academic_sessions,id',
                function ($attribute, $value, $fail) use ($school) {
                    $session = AcademicSession::find($value);
                    if ($session && (string) $session->school_id !== (string) $school->id) {
                        $fail('The selected academic session does not belong to your school.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique(Term::class, 'name')
                    ->where('academic_session_id', $this->input('academic_session_id')),
            ],
            'short_name' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'required_with:start_date', 'after:start_date'],
            'color' => ['nullable', 'string', 'max:9', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'options' => ['nullable', 'array'],
            'school_id' => ['prohibited'],
            'ordinal_number' => ['prohibited'],
            'state' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_session_id.required' => 'An academic session is required.',
            'name.required' => 'Term name is required.',
            'name.unique' => 'A term with this name already exists in the selected session.',
            'end_date.after' => 'End date must be after start date.',
            'ordinal_number.prohibited' => 'Sequence is assigned by the system.',
            'state.prohibited' => 'Lifecycle state cannot be set by the client.',
            'status.prohibited' => 'Lifecycle state cannot be set by the client.',
            'school_id.prohibited' => 'School ownership is derived from the academic session.',
        ];
    }
}
