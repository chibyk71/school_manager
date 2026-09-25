<?php

namespace App\Http\Requests\Address;

use App\Rules\InDynamicEnum;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates address update payloads for the managed Address API.
 */
class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryId = $this->input('country_id');
        $stateId = $this->input('state_id');

        return [
            'country_id' => ['nullable', 'exists:countries,id'],
            'state_id' => [
                'nullable',
                $countryId !== null && $countryId !== ''
                    ? 'exists:states,id,country_id,'.$countryId
                    : 'exists:states,id',
            ],
            'city_id' => [
                'nullable',
                $stateId !== null && $stateId !== ''
                    ? 'exists:cities,id,state_id,'.$stateId
                    : 'exists:cities,id',
            ],
            'address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city_text' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'type' => ['sometimes', 'string', new InDynamicEnum('address.type', GetSchoolModel())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_line_1' => 'address line 1',
            'address_line_2' => 'address line 2',
            'postal_code' => 'postal code',
            'city_text' => 'city text',
            'country_id' => 'country',
            'state_id' => 'state',
            'city_id' => 'city',
            'type' => 'address type',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type') && is_string($this->input('type'))) {
            $this->merge(['type' => DynamicEnumValue::canonicalize($this->input('type'))]);
        }
    }
}
