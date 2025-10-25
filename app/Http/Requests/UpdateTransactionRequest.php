<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('transactions.update', true);
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
            'transaction_type' => ['sometimes', 'in:income,expense'],
            'payable_id' => 'sometimes|nullable|integer',
            'payable_type' => 'sometimes|nullable|string',
            'category' => 'sometimes|string|max:100',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|nullable|string|in:cash,bank_transfer,card,cheque',
            'description' => 'sometimes|nullable|string|max:255',
            'transaction_date' => 'sometimes|date',
            'reference_number' => 'sometimes|nullable|string|max:100',
            'school_section_id' => ['sometimes', 'nullable', 'exists:school_sections,id,school_id,' . $school->id],
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