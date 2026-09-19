<?php

namespace App\Http\Requests\Address;

use App\Models\Address;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates partial address update payloads for the managed Address API.
 * Primary status is not accepted here — use dedicated setPrimary / unsetPrimary actions.
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
            'country_id' => ['sometimes', 'nullable', 'exists:countries,id'],
            'state_id' => [
                'sometimes',
                'nullable',
                $countryId !== null && $countryId !== ''
                    ? 'exists:states,id,country_id,'.$countryId
                    : 'exists:states,id',
            ],
            'city_id' => [
                'sometimes',
                'nullable',
                $stateId !== null && $stateId !== ''
                    ? 'exists:cities,id,state_id,'.$stateId
                    : 'exists:cities,id',
            ],
            'address_line_1' => ['sometimes', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city_text' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'type' => ['sometimes', 'string', new InDynamicEnum('type', Address::class)],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
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
}
