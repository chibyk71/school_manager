/**
 * Address Module Phase 4 — frontend types.
 *
 * Address Type values come from runtime Dynamic Enum (useDynamicEnums).
 * Structural typing remains; hardcoded ADDRESS_TYPE_OPTIONS is removed as authoritative source.
 */

/** Structural type for address type strings (runtime values from Dynamic Enum). */
export type AddressType = string;

export interface Address {
    id: string;
    type: AddressType | null;
    address_line_1: string;
    address_line_2: string | null;
    landmark: string | null;
    postal_code: string | null;
    country_id: number | null;
    state_id: number | null;
    city_id: number | null;
    city_text: string | null;
    is_primary: boolean;
    formatted?: string;
    country?: { id: number; name: string } | null;
    state?: { id: number; name: string } | null;
    city?: { id: number; name: string } | null;
}

/**
 * Form v-model shape for AddressForm (create & edit).
 * Matches HasAddress / StoreAddressRequest fillable fields.
 * Coordinates intentionally omitted from ordinary form surface.
 */
export interface AddressFormData {
    country_id: number | null;
    state_id: number | null;
    city_id: number | null;
    address_line_1: string;
    address_line_2: string | null;
    landmark: string | null;
    city_text: string | null;
    postal_code: string | null;
    type: AddressType | null;
    is_primary: boolean;
}

/** Draft address in embedded mode may lack id until owner is persisted. */
export type AddressInput = AddressFormData & { id?: string | null };

export function emptyAddressFormData(overrides: Partial<AddressFormData> = {}): AddressFormData {
    return {
        country_id: null,
        state_id: null,
        city_id: null,
        address_line_1: '',
        address_line_2: null,
        landmark: null,
        city_text: null,
        postal_code: null,
        type: null,
        is_primary: false,
        ...overrides,
    };
}

export function addressToFormData(address: Address | AddressFormData): AddressFormData {
    return {
        country_id: address.country_id ?? null,
        state_id: address.state_id ?? null,
        city_id: address.city_id ?? null,
        address_line_1: address.address_line_1 ?? '',
        address_line_2: address.address_line_2 ?? null,
        landmark: address.landmark ?? null,
        city_text: address.city_text ?? null,
        postal_code: address.postal_code ?? null,
        type: address.type ?? null,
        is_primary: Boolean(address.is_primary),
    };
}
