<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateSalaryStructureRequest extends FormRequest
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
            'salary_id' => 'sometimes|exists:salaries,id',
            'department_role_id' => 'sometimes|exists:department_roles,id',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|in:NGN,USD,GBP',
            'effective_date' => 'sometimes|date',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
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
        if ($this->has('effective_date')) {
            $this->merge(['effective_date' => Carbon::parse($this->effective_date)]);
        }
    }
}