<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasPermissionTo('update-school');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:schools,slug,' . $this->school->id,
            'email' => 'required|email|max:255|unique:schools,email,' . $this->school->id,
            'phone_one' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
            'phone_two' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
            'type' => 'required|string|in:private,government,community',
            'logo' => 'nullable|string',
            'extra_data' => 'nullable|array',
        ];
    }
}
