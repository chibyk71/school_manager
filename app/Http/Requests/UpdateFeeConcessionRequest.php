<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeConcessionRequest extends FormRequest
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
        $feeConcessionId = $this->route('feeConcession') ? $this->route('feeConcession')->id : $this->input('id');
        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:fee_concessions,name,' . $feeConcessionId . ',id,school_id,' . $school->id],
            'description' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|in:amount,percent',
            'amount' => 'sometimes|numeric|min:0',
            'fee_type_id' => ['sometimes', 'exists:fee_types,id,school_id,' . $school->id],
            'start_date' => 'sometimes|nullable|date|after_or_equal:today',
            'end_date' => 'sometimes|nullable|date|after:start_date',
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