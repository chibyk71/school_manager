<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
            'required',
            Rule::unique('school_sections', 'name')->ignore($this->route('schoolSection')),
            ],
            'display_name' => 'nullable|string',
            'description' => 'nullable|string',
            'school_id' => 'sometimes|exists:schools,id',
        ];
    }
}
