<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleExpenseRequest extends FormRequest
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
        return [
            'amount' => 'sometimes|required|numeric|min:0',
            'liters' => 'nullable|numeric|min:0',
            'date_of_expense' => 'sometimes|required|date',
            'next_due_date' => 'nullable|date|after_or_equal:date_of_expense',
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];
    }
}
