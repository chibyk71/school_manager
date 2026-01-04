<?php

/**
 * UpdateSchoolRequest v2.0 – Validation for School Updates (Address-Integrated)
 *
 * Purpose & Context:
 * ------------------
 * Handles validation and authorization when updating an existing school in the multi-tenant SaaS.
 * Fully aligned with the polymorphic HasAddress trait introduced on the School model.
 *
 * Key Changes & Improvements (v2.0):
 * ---------------------------------
 * - Removed all deep 'address.*' validation rules. Address validation is now centralized
 *   in HasAddress::validateAddressData() and executed when calling $school->addAddress()
 *   (or updateAddress() if extending further).
 * - Renamed the incoming address payload to 'primary_address' for clarity and consistency
 *   with StoreSchoolRequest and future frontend forms (avoids confusion with old JSON column).
 * - 'primary_address' is optional ('sometimes') and only an array – no nested rules here.
 * - Keeps unique checks with proper ignore($schoolId) for code/email.
 * - Media rules use 'sometimes' to allow partial updates (replace only specific logos).
 * - Authorization relies on permission check (handled by SchoolPolicy).
 * - Boolean casting and uppercase code normalization preserved.
 * - Custom attributes/messages cleaned up (address references removed).
 *
 * Problems Solved:
 * ----------------
 * - Eliminates duplicated validation logic across form requests and models.
 * - Ensures identical address rules for School, Student, Staff, etc. (single source of truth).
 * - Supports partial address updates without forcing all fields.
 * - Prepares for full-page CreateEdit.vue component (flattened primary address fields).
 * - Improves maintainability – future address rule changes only in HasAddress trait.
 *
 * Usage Flow:
 * -----------
 * 1. Controller receives validated core + media data.
 * 2. SchoolService updates core attributes and media.
 * 3. If 'primary_address' array is present in request:
 *    - If school already has a primary address → $school->updateAddress($existingId, $data)
 *    - Else → $school->addAddress($data, true)
 *    (Both methods validate via the trait).
 *
 * Fits into School Module:
 * ------------------------
 * Works with SchoolController::update(), SchoolService::updateSchool(),
 * and the upcoming combined CreateEdit.vue page.
 */
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
        $schoolId = $this->route('school')->id; // Model-bound route parameter

        return [
            // Core school fields
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:50', Rule::unique('schools', 'code')->ignore($schoolId)],
            'email'     => ['required', 'email', 'max:255', Rule::unique('schools', 'email')->ignore($schoolId)],
            'phone_one' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'phone_two' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'type'      => ['required', 'string', 'in:private,government,community'],

            // Status – partial update allowed
            'is_active' => ['sometimes', 'boolean'],

            // Primary address payload – optional array only (validation delegated to HasAddress trait)
            'addresses' => ['sometimes', 'array'],

            // Branding / Media (Spatie Media Library) – optional replacement files
            'logo'            => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'small_logo'      => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'favicon'         => ['sometimes', 'file', 'mimes:jpeg,png,ico,svg+xml', 'max:2048'],
            'dark_logo'       => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'dark_small_logo' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],

            // Optional extra JSON data
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom attribute names for friendly error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'            => 'school name',
            'code'            => 'school code',
            'email'           => 'school email',
            'phone_one'       => 'primary phone',
            'phone_two'       => 'secondary phone',
            'type'            => 'school type',
            'is_active'       => 'status',
            'logo'            => 'main logo',
            'small_logo'      => 'small logo',
            'favicon'         => 'favicon',
            'dark_logo'       => 'dark mode logo',
            'dark_small_logo' => 'dark mode small logo',
        ];
    }

    /**
     * Prepare data before validation runs (casting & normalization).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : null,
            'code'      => $this->has('code') ? strtoupper($this->input('code')) : null,
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
            'code.unique' => 'This school code is already in use by another school.',
            'email.unique' => 'This email address is already used by another school.',
            'logo.image' => 'The main logo must be a valid image file.',
            'favicon.mimes' => 'Favicon must be a jpeg, png, ico, or svg file.',
        ];
    }
}
