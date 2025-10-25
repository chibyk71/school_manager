<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeConcessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
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
            'name' => ['required', 'string', 'max:255', 'unique:fee_concessions,name,NULL,id,school_id,' . $school->id],
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:amount,percent',
            'amount' => 'required|numeric|min:0',
            'fee_type_id' => ['required', 'exists:fee_types,id,school_id,' . $school->id],
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => ['exists:users,id,school_id,' . $school->id],
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