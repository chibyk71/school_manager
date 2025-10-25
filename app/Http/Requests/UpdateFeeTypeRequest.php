<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeTypeRequest extends FormRequest
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
        $feeTypeId = $this->route('feeType') ? $this->route('feeType')->id : $this->input('id');
        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:fee_types,name,' . $feeTypeId . ',id,school_id,' . $school->id],
            'description' => 'sometimes|nullable|string|max:255',
            'color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'options' => 'sometimes|nullable|array',
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