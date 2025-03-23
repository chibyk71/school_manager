<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'class_level_id' => ['required', 'integer', 'exists:class_levels,id'],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
