<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateSalaryAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'staff_id' => 'sometimes|uuid|exists:staff,id',
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:bonus,allowance,overtime,deduction',
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|string|nullable',
            'effective_date' => 'sometimes|date',
            'recurrence' => 'sometimes|in:one-time,daily,weekly,monthly|nullable',
            'recurrence_end_date' => 'sometimes|date|after_or_equal:effective_date|nullable',
        ];
    }

    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
        if ($this->has('effective_date')) {
            $this->merge(['effective_date' => Carbon::parse($this->effective_date)]);
        }
        if ($this->has('recurrence_end_date')) {
            $this->merge(['recurrence_end_date' => Carbon::parse($this->recurrence_end_date)]);
        }
    }
}