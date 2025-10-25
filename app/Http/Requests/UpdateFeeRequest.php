<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('edit-fees', true);
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
            'fee_type_id' => ['sometimes', 'exists:fee_types,id,school_id,' . $school->id],
            'term_id' => ['sometimes', 'exists:terms,id,school_id,' . $school->id],
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|nullable|string|max:255',
            'due_date' => 'sometimes|date|after_or_equal:today',
            'branch_id' => ['sometimes', 'nullable', 'exists:branches,id,school_id,' . $school->id],
            'class_section_ids' => 'sometimes|nullable|array',
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