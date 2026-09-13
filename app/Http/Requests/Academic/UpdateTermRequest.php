<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HTTP-layer input shape for updating a Term (Phase 3).
 *
 * Domain rules belong to TermLifecycleService. No client-controlled state or sequence.
 */
class UpdateTermRequest extends FormRequest
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

        $term = $this->route('term');
        if (! $term instanceof Term) {
            throw ValidationException::withMessages([
                'term' => 'Invalid term route parameter.',
            ]);
        }

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique(Term::class, 'name')
                    ->where('academic_session_id', $term->academic_session_id)
                    ->ignore($term->id),
            ],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:10'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'start_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after:start_date'],
            'color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'options' => ['sometimes', 'nullable', 'array'],
            'academic_session_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'ordinal_number' => ['prohibited'],
            'state' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Term name is required.',
            'name.unique' => 'A term with this name already exists in this session.',
            'end_date.after' => 'End date must be after start date.',
            'ordinal_number.prohibited' => 'Sequence is managed by the system.',
            'state.prohibited' => 'Lifecycle state cannot be set by the client.',
            'status.prohibited' => 'Lifecycle state cannot be set by the client.',
            'academic_session_id.prohibited' => 'Parent session cannot be changed via update.',
            'school_id.prohibited' => 'School ownership is derived from the academic session.',
        ];
    }
}
