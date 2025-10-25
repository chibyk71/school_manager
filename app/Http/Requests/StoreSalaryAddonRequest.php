<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreSalaryAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'staff_id' => 'required|uuid|exists:staff,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:bonus,allowance,overtime,deduction',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'effective_date' => 'required|date',
            'recurrence' => 'nullable|in:one-time,daily,weekly,monthly',
            'recurrence_end_date' => 'nullable|date|after_or_equal:effective_date',
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