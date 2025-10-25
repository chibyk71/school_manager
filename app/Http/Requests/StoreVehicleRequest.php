<?php

namespace App\Http\Requests;

use App\Models\Employee\Staff;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
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
        $school = GetSchoolModel();
        return [
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:50|unique:vehicles,registration_number',
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'max_seating_capacity' => 'required|integer|min:1',
            'is_owned' => 'required|boolean',
            'owner_name' => 'required_if:is_owned,false|string|max:255|nullable',
            'owner_company_name' => 'nullable|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|email|max:255',
            'vehicle_fuel_type_id' => 'nullable|exists:vehicle_fuel_types,id',
            'max_fuel_capacity' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
            'options' => 'nullable|array',
            'staff_id' => [
                'nullable',
                'exists:staff,id',
                function ($attribute, $value, $fail) use ($school) {
                    $staff = Staff::find($value);
                    if ($staff && $staff->school_id !== $school->id) {
                        $fail('The selected staff must belong to the current school.');
                    }
                },
            ],
            'effective_date' => 'nullable|date',
            'driver_options' => 'nullable|array',
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
    }
}
