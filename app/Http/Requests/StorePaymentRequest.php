<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('payments.create', true);
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
            'user_id' => ['required', 'exists:users,id,school_id,' . $school->id],
            'payment_method' => 'required|string|max:255',
            'payment_status' => 'required|in:pending,success,failed',
            'payment_amount' => 'required|numeric|min:0',
            'payment_currency' => 'required|string|size:3', // e.g., NGN, USD
            'payment_reference' => 'required|string|max:255|unique:payments,payment_reference',
            'payment_date' => 'required|date',
            'payment_description' => 'required|string|max:1000',
            'fee_installment_detail_id' => ['nullable', 'exists:fee_installment_details,id,school_id,' . $school->id],
            'fee_id' => ['nullable', 'exists:fees,id,school_id,' . $school->id],
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
        if (!$this->has('id')) {
            $this->merge(['id' => (string) Str::uuid()]);
        }
    }
}