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
 * - Authorization enforces SchoolPolicy::create (auth middleware alone is not enough).
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

use App\Support\School\SchoolTypeCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Enforce SchoolPolicy::create — route auth middleware only proves authentication.
        return $this->user() !== null
            && $this->user()->can('create', \App\Models\School::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Phase 1 Draft creation: only name is required.
            // code/email/type/contact become required for Ready (readiness evaluator).
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:schools,code'],
            'email' => ['nullable', 'email', 'max:255', 'unique:schools,email'],
            'phone_one' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'phone_two' => ['nullable', 'string', 'max:30', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(SchoolTypeCatalogue::values())],
            'description' => ['nullable', 'string', 'max:1000'],
            'motto' => ['nullable', 'string', 'max:255'],

            // Lifecycle state is never client-assignable on create/update.
            'state' => ['prohibited'],
            'is_active' => ['prohibited'],

            // Media files (Spatie Media Library)
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'small_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:1024'],
            'favicon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico,webp', 'max:512'],
            'dark_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'dark_small_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:1024'],

            // Nested addresses for HasAddress (validated in trait on persist)
            'addresses' => ['nullable', 'array'],
            'addresses.*.line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.state' => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'string', 'max:100'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],

            // Optional admin assignment on create
            'admin_id' => ['nullable', 'uuid'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'admin_password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'school name',
            'code' => 'school code',
            'email' => 'school email',
            'contact_name' => 'primary contact name',
            'type' => 'school type',
        ];
    }
}
