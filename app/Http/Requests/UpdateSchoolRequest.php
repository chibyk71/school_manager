<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('school'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $school = $this->route('school');

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('schools', 'slug')->ignore($school->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('schools', 'email')->ignore($school->id),
            ],
            'phone_one' => 'required|string|max:15',
            'phone_two' => 'nullable|string|max:15',
            'logo' => 'nullable|string|max:2048',
            'tenancy_type' => 'required|in:private,government,community',
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('schools', 'id')->where(function ($query) use ($school) {
                    $query->where('id', '!=', $school->id); // Prevent self-referencing
                }),
            ],
            'data' => 'nullable|array',
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
        ];
    }
}
