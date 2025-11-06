<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();

        return [
            'name'        => [
                'required',
                'regex:/^[a-z_]+$/',
                Rule::unique('configs')->where(function ($query) use ($school) {
                    $query->where('applies_to', $this->applies_to)
                          ->where('scope_type', $this->is_system ? null : School::class)
                          ->where('scope_id', $this->is_system ? null : $school?->id);
                }),
            ],
            'applies_to'  => 'required|string', // e.g., App\Models\School
            'label'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'color'       => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'options'     => 'required|array|min:1',
            'options.*'   => 'required|string',
        ];
    }
}
