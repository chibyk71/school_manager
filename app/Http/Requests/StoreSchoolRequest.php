<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('school.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Core school fields
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:schools,code'],
            'email' => ['required', 'email', 'max:255', 'unique:schools,email'],
            'phone_one' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'phone_two' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'type' => ['required', 'string', 'in:private,government,community'],

            // Status
            'is_active' => ['sometimes', 'boolean'],

            // Primary address (nested array)
            'address' => ['required', 'array'],
            'address.address' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'address.phone_number' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],

            // Branding / Media (Spatie Media Library – expect uploaded files)
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg', 'max:5120'], // 5MB
            'small_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg', 'max:5120'],
            'favicon' => ['nullable', 'file', 'mimes:jpeg,png,ico,svg', 'max:2048'], // 2MB
            'dark_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg', 'max:5120'],
            'dark_small_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg', 'max:5120'],

            // Optional extra data (JSON column)
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom attribute names for better error messages.
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
     * Ensures boolean casting and default values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
            'code' => strtoupper($this->input('code')), // Optional: enforce uppercase codes
        ]);
    }

    /**
     * Custom messages (optional – add if you want more friendly errors).
     */
    public function messages(): array
    {
        return [
            'address.country_id.exists' => 'The selected country is invalid.',
            'logo.image' => 'The main logo must be an image file.',
            'favicon.mimes' => 'Favicon must be jpeg, png, ico or svg.',
            'code.unique' => 'This school code is already taken.',
            'email.unique' => 'This email is already used by another school.',
        ];
    }
}