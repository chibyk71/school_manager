<?php

namespace App\Http\Requests;

use App\Models\Employee\Staff;
use Illuminate\Foundation\Http\FormRequest;

class AssignDriverRequest extends FormRequest
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
            'staff_id' => [
                'required',
                'exists:staff,id',
                function ($attribute, $value, $fail) use ($school) {
                    $staff = Staff::find($value);
                    if ($staff && $staff->school_id !== $school->id) {
                        $fail('The selected staff must belong to the current school.');
                    }
                },
            ],
            'effective_date' => 'nullable|date',
            'options' => 'nullable|array',
        ];
    }
}
