<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('payments.update', true);
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
            'user_id' => ['sometimes', 'exists:users,id,school_id,' . $school->id],
            'payment_method' => 'sometimes|string|max:255',
            'payment_status' => 'sometimes|in:pending,success,failed',
            'payment_amount' => 'sometimes|numeric|min:0',
            'payment_currency' => 'sometimes|string|size:3',
            'payment_reference' => 'sometimes|string|max:255|unique:payments,payment_reference,' . $this->payment->id,
            'payment_date' => 'sometimes|date',
            'payment_description' => 'sometimes|string|max:1000',
            'fee_installment_detail_id' => ['sometimes', 'nullable', 'exists:fee_installment_details,id,school_id,' . $school->id],
            'fee_id' => ['sometimes', 'nullable', 'exists:fees,id,school_id,' . $school->id],
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