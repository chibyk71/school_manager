<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('create-fees', true);
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
            'fee_type_id' => ['required', 'exists:fee_types,id,school_id,' . $school->id],
            'term_id' => ['required', 'exists:terms,id,school_id,' . $school->id],
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'due_date' => 'required|date|after_or_equal:today',
            'branch_id' => ['nullable', 'exists:branches,id,school_id,' . $school->id],
            'class_section_ids' => 'nullable|array',
            'class_section_ids.*' => ['exists:class_sections,id,school_id,' . $school->id],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }
}