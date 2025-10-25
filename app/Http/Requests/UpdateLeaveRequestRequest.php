<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateLeaveRequestRequest extends FormRequest
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
            'leave_type_id' => 'sometimes|exists:leave_types,id',
            'reason' => 'sometimes|string|nullable',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:pending,approved,rejected',
            'rejected_reason' => 'required_if:status,rejected|string|nullable',
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
        if ($this->has('start_date')) {
            $this->merge(['start_date' => Carbon::parse($this->start_date)]);
        }
        if ($this->has('end_date')) {
            $this->merge(['end_date' => Carbon::parse($this->end_date)]);
        }
    }
}