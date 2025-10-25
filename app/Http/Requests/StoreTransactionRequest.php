<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('transactions.create', true);
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
            'transaction_type' => ['required', 'in:income,expense'],
            'payable_id' => 'nullable|integer',
            'payable_type' => 'nullable|string',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,card,cheque',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'school_section_id' => ['nullable', 'exists:school_sections,id,school_id,' . $school->id],
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