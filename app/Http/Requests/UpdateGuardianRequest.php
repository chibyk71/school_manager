<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Request validation for updating a guardian.
 *
 * @package App\Http\Requests
 */
class UpdateGuardianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update-guardians');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = $this->getActiveSchool();

        return [
            'user_id' => 'sometimes|uuid|exists:users,id',
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable|string|max:255',
            'children' => 'nullable|array',
            'children.*' => 'uuid|exists:students,id,school_id,' . $school->id,
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'custom_fields' => 'custom fields',
            'children' => 'children',
        ];
    }

    /**
     * Get the active school model.
     *
     * @return \App\Models\School
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            Log::error('No active school found during guardian update.');
            throw ValidationException::withMessages(['school' => 'No active school found.']);
        }
        return $school;
    }
}