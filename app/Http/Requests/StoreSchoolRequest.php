<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasPermissionTo('create-school');
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
            'slug' => 'nullable|string|max:255|unique:schools,slug',
            'email' => 'required|email|max:255|unique:schools,email',
            'phone_one' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
            'phone_two' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
            'type' => 'required|string|in:private,government,community',
            'logo' => 'nullable|string',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'address' => 'required|array',
            'address.address' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.lga' => 'nullable|string|max:255',
            'address.state' => 'required|string|max:255',
            'address.country' => 'required|string|max:255',
            'address.postal_code' => 'nullable|string|max:20',
            'address.phone_number' => 'nullable|string|max:20',
            'extra_data' => 'nullable|array',
        ];
    }
}
