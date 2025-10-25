<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request class for storing a new hostel.
 *
 * Validates input data for creating a hostel, ensuring it is scoped to the active school
 * and adheres to the required constraints in a multi-tenant SaaS environment.
 */
class StoreHostelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check(); // Authorization handled in controller via permitted()
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
            'description' => 'nullable|string',
            'staff_id' => [
                'nullable',
                'exists:staff,id,school_id,' . ($school ? $school->id : 0),
            ],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Automatically adds the school_id to the request data if not provided.
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
