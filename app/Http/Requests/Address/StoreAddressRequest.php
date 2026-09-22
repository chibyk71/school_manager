<?php

namespace App\Http\Requests\Address;

use App\Models\Address;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates address creation payloads for the managed Address API.
 * Domain hierarchy and Dynamic Enum rules match HasAddress::validateAddressData.
 * is_primary is accepted only as an explicit capability flag (not arbitrary mass-assignment).
 */
class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is performed in AddressController against the resolved owner.
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
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city_text' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'string', new InDynamicEnum('type', Address::class)],
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
}
