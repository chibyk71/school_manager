<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check(); // Authorization handled in controller via policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:terms,name,NULL,id,school_id,' . $school->id . ',academic_session_id,' . $this->input('academic_session_id'),
            ],
            'description' => 'nullable|string',
            'start_date' => [
                'required',
                'date',
                function ($attr, $value, $fail) {
                    $this->validateTermDates($attr, $value, $fail);
                },
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                function ($attr, $value, $fail) {
                    $this->validateTermDates($attr, $value, $fail);
                },
            ],
            'status' => 'required|in:active,pending,inactive',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'options' => 'nullable|array',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }

    protected function validateTermDates($attribute, $value, $fail)
    {
        $sessionId = $this->input('academic_session_id');
        $session = \App\Models\Academic\AcademicSession::find($sessionId);
        if (!$session)
            return;

        $start = $this->input('start_date');
        $end = $this->input('end_date');

        // 1. Inside session bounds
        if ($attribute === 'start_date' && $value < $session->start_date) {
            $fail('Start date must be >= session start.');
        }
        if ($attribute === 'end_date' && $value > $session->end_date) {
            $fail('End date must be <= session end.');
        }

        // 2. No overlap with other terms
        $overlap = \App\Models\Academic\Term::where('academic_session_id', $sessionId)
            ->where('id', '!=', $this->route('term')?->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhereRaw('? BETWEEN start_date AND end_date', [$start])
                    ->orWhereRaw('? BETWEEN start_date AND end_date', [$end]);
            })->exists();

        if ($overlap) {
            $fail('Term dates overlap with another term in this session.');
        }
    }
}
