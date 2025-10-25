<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
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
        $departmentId = $this->route('department') ? $this->route('department')->id : $this->input('id');
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'unique:departments,name,' . $departmentId . ',id,school_id,' . $school->id,
            ],
            'category' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'effective_date' => 'sometimes|date|after_or_equal:today',
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