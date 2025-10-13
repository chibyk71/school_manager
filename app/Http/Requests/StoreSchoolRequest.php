<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', School::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:schools,slug|max:255',
            'email' => 'required|email|unique:schools,email|max:255',
            'phone_one' => 'required|string|max:15',
            'phone_two' => 'nullable|string|max:15',
            'logo' => 'nullable|string|max:2048', // Assuming logo is a URL or path
            'tenancy_type' => 'required|in:private,government,community',
            'parent_id' => 'nullable|uuid|exists:schools,id',
            'data' => 'nullable|array',
            'admin_name' => 'required|string|max:255',
            'admin_password' => 'required|string|min:8',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure tenancy_type is lowercase
        if ($this->has('tenancy_type')) {
            $this->merge([
                'tenancy_type' => strtolower($this->input('tenancy_type')),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'school name',
            'phone_one' => 'primary phone',
            'phone_two' => 'secondary phone',
            'tenancy_type' => 'school type',
            'parent_id' => 'parent school',
            'admin_name' => 'admin name',
            'admin_password' => 'admin password',
        ];
    }
}
