<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeInstallmentDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return permitted('fee-installment-details.update', true);
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
            'fee_installment_id' => ['sometimes', 'exists:fee_installments,id,school_id,' . $school->id],
            'user_id' => ['sometimes', 'exists:users,id,school_id,' . $school->id],
            'amount' => 'sometimes|numeric|min:0',
            'due_date' => 'sometimes|date|after_or_equal:today',
            'status' => 'sometimes|in:pending,paid,overdue',
            'paid_date' => 'sometimes|nullable|date',
            'punishment' => 'sometimes|nullable|numeric|min:0',
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