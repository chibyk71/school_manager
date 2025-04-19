<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if the user has permission to create a department
        // You can customize this logic based on your authorization system
        // For example, you might check if the user has a specific role or permission
        // auth()->user()->ability(
        //     ['super-admin', 'hr-manager'],
        //     ['create-department']
        // );
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
            'school_id' => ['nullable', 'exists:schools,id'],
        ];
    }
}
