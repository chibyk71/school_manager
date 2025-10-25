<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTermRequest extends FormRequest
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
        $termId = $this->route('term') ? $this->route('term')->id : $this->input('id');
        return [
            'academic_session_id' => 'sometimes|exists:academic_sessions,id',
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'unique:terms,name,' . $termId . ',id,school_id,' . $school->id . ',academic_session_id,' . ($this->input('academic_session_id') ?? $this->route('term')?->academic_session_id),
            ],
            'description' => 'nullable|string',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:active,pending,inactive',
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
}