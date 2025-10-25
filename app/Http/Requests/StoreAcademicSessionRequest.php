<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\School;

/**
 * Request class for storing a new academic session.
 *
 * Validates input data for creating an academic session, ensuring it belongs to the active school.
 *
 * @package App\Http\Requests
 */
class StoreAcademicSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller using permitted()
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        $schoolId = $school ? $school->id : null;

        return [
            'name' => ['nullable', 'string', 'max:255', "unique:academic_sessions,name,NULL,id,school_id,{$schoolId}"],
            'start_date' => ['required', 'date', 'before:end_date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['boolean'],
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
        if ($school) {
            $this->merge([
                'school_id' => $school->id,
            ]);
        }
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'The session name is already in use for this school.',
            'start_date.before' => 'The start date must be before the end date.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }
}