<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdatePayrollRequest extends FormRequest
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
            'user_id' => 'sometimes|exists:users,id',
            'salary_id' => 'sometimes|exists:salaries,id',
            'bonus' => 'sometimes|numeric|min:0|nullable',
            'deduction' => 'sometimes|numeric|min:0|nullable',
            'payment_date' => 'sometimes|date',
            'description' => 'sometimes|string|nullable',
            'status' => 'sometimes|in:paid,unpaid',
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
        if ($this->has('payment_date')) {
            $this->merge(['payment_date' => Carbon::parse($this->payment_date)]);
        }
    }
}