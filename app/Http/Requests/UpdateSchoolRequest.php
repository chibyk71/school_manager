<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('school.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->route('school')->id; // or $this->school->id if bound

        return [
            // Core school fields
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('schools', 'code')->ignore($schoolId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('schools', 'email')->ignore($schoolId)],
            'phone_one' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'phone_two' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'type' => ['required', 'string', 'in:private,government,community'],

            // Status – allow partial update (e.g., toggle)
            'is_active' => ['sometimes', 'boolean'],

            // Primary address (nested array – only validated if provided)
            'address' => ['sometimes', 'required', 'array'],
            'address.address' => ['required_with:address', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:100'],
            'address.state' => ['required_with:address', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country_id' => ['required_with:address', 'integer', Rule::exists('countries', 'id')],
            'address.phone_number' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],

            // Branding / Media uploads (Spatie Media Library – files are optional)
            'logo' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'], // 5MB
            'small_logo' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'favicon' => ['sometimes', 'file', 'mimes:jpeg,png,ico,svg+xml', 'max:2048'], // 2MB
            'dark_logo' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'dark_small_logo' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],

            // Optional JSON data column
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom attribute names for user-friendly error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'school name',
            'code' => 'school code',
            'email' => 'school email',
            'phone_one' => 'primary phone',
            'phone_two' => 'secondary phone',
            'type' => 'school type',
            'is_active' => 'status',
            'address.address' => 'address line',
            'address.city' => 'city',
            'address.state' => 'state',
            'address.postal_code' => 'postal code',
            'address.country_id' => 'country',
            'address.phone_number' => 'address phone',
            'logo' => 'main logo',
            'small_logo' => 'small logo',
            'favicon' => 'favicon',
            'dark_logo' => 'dark mode logo',
            'dark_small_logo' => 'dark mode small logo',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Cast boolean and normalize values before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : null,
            'code' => $this->has('code') ? strtoupper($this->input('code')) : null,
        ]);
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.country_id.exists' => 'The selected country is invalid.',
            'code.unique' => 'This school code is already in use by another school.',
            'email.unique' => 'This email address is already used by another school.',
            'logo.image' => 'The main logo must be a valid image file.',
            'favicon.mimes' => 'Favicon must be a jpeg, png, ico, or svg file.',
        ];
    }
}