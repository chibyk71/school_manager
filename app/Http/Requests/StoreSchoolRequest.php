<?php

/**
 * StoreSchoolRequest v2.0 – Validation for School Creation (Address-Integrated)
 *
 * Purpose & Context:
 * ------------------
 * Handles validation and authorization for creating a new school in the multi-tenant SaaS.
 * Updated to integrate with the polymorphic HasAddress trait – address fields are no longer
 * validated here (centralized in the trait for reuse across all addressable models).
 *
 * Key Changes & Improvements:
 * ---------------------------
 * - Removed all 'address.*' nested rules: validation now occurs in HasAddress::validateAddressData()
 *   during $school->addAddress() call in SchoolService.
 * - Keeps core school fields, media rules, and unique checks.
 * - Authorization simplified: relies on policy/permission (public onboarding handled separately if needed).
 * - Boolean casting and code uppercase enforcement preserved.
 * - Custom attributes/messages updated to remove address references.
 *
 * Problems Solved:
 * ----------------
 * - Eliminates validation duplication (DRY principle).
 * - Ensures consistent address rules across Student, Staff, School, etc.
 * - Allows HasAddress to handle hierarchical (country/state/city) and geolocation validation.
 * - Prepares for full-page create/edit forms (data passed flattened or nested to service).
 *
 * Usage Flow:
 * -----------
 * - Controller/SchoolService receives validated core data.
 * - After school creation: if 'primary_address' array present in request,
 *   call $school->addAddress($request->input('primary_address'), true).
 *
 * Fits into School Module:
 * ------------------------
 * Works with SchoolController::store(), SchoolService::createSchool(), and upcoming CreateEdit.vue page.
 * Extensible for future fields (e.g., subscription_plan).
 */
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
        // Handled via policy or middleware; adjust if public onboarding allowed
        return true; // Or auth()->user()?->hasPermission('school.create')
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

            // Primary address – now optional array (validated in HasAddress trait)
            'addresses' => ['sometimes', 'array'], // No deep rules here

            // Branding / Media (Spatie Media Library)
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'small_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'favicon' => ['nullable', 'file', 'mimes:jpeg,png,ico,svg+xml', 'max:2048'],
            'dark_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],
            'dark_small_logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,svg+xml', 'max:5120'],

            // Optional extra data
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom attribute names for error messages.
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
            'logo' => 'main logo',
            'small_logo' => 'small logo',
            'favicon' => 'favicon',
            'dark_logo' => 'dark mode logo',
            'dark_small_logo' => 'dark mode small logo',
        ];
    }

    /**
     * Prepare data for validation (casting, normalization).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
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
            'logo.image' => 'The main logo must be a valid image file.',
            'favicon.mimes' => 'Favicon must be jpeg, png, ico, or svg.',
            'code.unique' => 'This school code is already taken.',
            'email.unique' => 'This email is already used by another school.',
        ];
    }
}
