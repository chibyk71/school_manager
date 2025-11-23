<?php

namespace App\Http\Requests;

use App\Support\DepartmentCategories;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:departments,name,NULL,id,school_id,' . $school->id,
            ],
            'category' => 'required|string|in:' . implode(',', DepartmentCategories::getKeys()),
            'description' => 'nullable|string',
            'effective_date' => 'nullable|date|after_or_equal:today',
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