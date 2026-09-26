<?php

namespace App\Http\Requests;

use App\Rules\InDynamicEnum;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates personal profile updates (Phase 5 Dynamic Enum consumers).
 *
 * title → profile.title
 * gender → profile.gender
 *
 * School context is supplied explicitly via GetSchoolModel() to InDynamicEnum.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return [
            'title' => ['nullable', 'string', 'max:30', new InDynamicEnum('profile.title', $school)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:30', new InDynamicEnum('profile.gender', $school)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('title') && is_string($this->input('title'))) {
            $payload['title'] = DynamicEnumValue::canonicalize($this->input('title'));
        }

        if ($this->has('gender') && is_string($this->input('gender'))) {
            $payload['gender'] = DynamicEnumValue::canonicalize($this->input('gender'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
