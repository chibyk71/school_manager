// resources/js/types/address.ts
/**
 * address.ts v2.0 – Centralized TypeScript Definitions for Address Management Module
 *
 * Purpose & Problems Solved:
 * - Single source of truth for all address-related types across the entire frontend application.
 * - Guarantees perfect alignment with the backend App\Models\Address model (v4.0) and the addresses migration.
 * - Provides strict type safety for Inertia page props, API responses, form v-models, and component props/emits.
 * - Supports polymorphic usage while exposing useful display helpers (formatted address, type label).
 * - Defines a strict union type for the 'type' field (matching backend validation rules).
 * - Supplies ready-to-use dropdown options and label helper for AddressForm.vue and any future select components.
 * - Includes optional school_id (multi-tenant) – hidden from forms but available when needed (e.g., DataTables).
 * - Prevents "any" leakage and field name mismatches throughout the module (AddressManager.vue, AddressList.vue, AddressModal.vue, etc.).
 * - Enables consistent handling of nullable fields, relations, and accessors.
 *
 * Key Changes in v2.0:
 * - Added school_id (UUID | null) to Address interface to reflect BelongsToSchool trait and migration.
 * - Bumped version and refreshed documentation to match current backend implementation.
 * - Ensured AddressFormData matches exactly what is fillable/validated in HasAddress trait (no school_id, no morph fields).
 * - Kept ADDRESS_TYPE_OPTIONS exhaustive and type-safe using 'satisfies'.
 *
 * Fits into the Address Management Module:
 * - Used by all address-related components (AddressForm.vue, AddressModal.vue, AddressManager.vue, AddressList.vue).
 * - Consumed by useAddress composable (future) and any DataTable configurations.
 * - Critical for type-safe communication between backend (Inertia props / JSON responses) and frontend.
 *
 * Usage Examples:
 *   import type { Address, AddressFormData, AddressType } from '@/types/address';
 *
 *   const addresses: Address[] = page.props.addresses as Address[];
 *   const form = ref<AddressFormData>({ country_id: null, address_line_1: '', is_primary: false, ... });
 *   const label = getAddressTypeLabel(address.type);
 *
 * Dependencies:
 * - None (pure TypeScript – zero runtime overhead).
 * - Assumes nnjeim/world types are imported separately if relations are used deeply.
 */

export type AddressType =
    | 'residential'
    | 'school_campus'
    | 'office'
    | 'postal'
    | 'temporary'
    | 'billing'
    | 'other';

export interface Address {
    /** UUID primary key */
    id: string;

    /** Multi-tenant scoping – nullable for global/shared addresses (rare) */
    school_id?: string | null;

    /** Polymorphic owner */
    addressable_type: string;
    addressable_id: string | number;

    /** nnjeim/world hierarchical references */
    country_id: number | null;
    state_id: number | null;
    city_id: number | null;

    /** Core address fields (Nigeria-first design) */
    address_line_1: string;
    address_line_2: string | null;
    landmark: string | null;
    city_text: string | null;
    postal_code: string | null;

    /** Classification */
    type: AddressType | null;

    /** Geolocation (optional) */
    latitude: number | null;
    longitude: number | null;

    /** Primary flag – managed by HasAddress trait */
    is_primary: boolean;

    /** Timestamps & soft deletes */
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;

    /** Optional eager-loaded relations */
    country?: { id: number; name: string };
    state?: { id: number; name: string };
    city?: { id: number; name: string };

    /** Backend accessor – human-readable full address */
    formatted?: string;
}

/**
 * Exact shape used for form v-model (create & edit).
 * Matches backend fillable fields and validation rules in HasAddress trait.
 * Excludes internal fields (id, school_id, morph fields, timestamps).
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

    latitude: number | null;
    longitude: number | null;

    is_primary: boolean;
}

/**
 * Dropdown options for address type – used in AddressForm.vue and any future selects.
 * Kept in sync with backend validation rule in HasAddress trait.
 */
export const ADDRESS_TYPE_OPTIONS = [
    { label: 'Residential', value: 'residential' as AddressType },
    { label: 'School Campus', value: 'school_campus' as AddressType },
    { label: 'Office', value: 'office' as AddressType },
    { label: 'Postal', value: 'postal' as AddressType },
    { label: 'Temporary', value: 'temporary' as AddressType },
    { label: 'Billing', value: 'billing' as AddressType },
    { label: 'Other', value: 'other' as AddressType },
] satisfies { label: string; value: AddressType }[];

/**
 * Helper to get human-readable label for an address type.
 * Used in AddressList.vue / AddressItem.vue for display.
 */
export const getAddressTypeLabel = (value: AddressType | null | undefined): string => {
    if (!value) return 'Not specified';

    return (
        ADDRESS_TYPE_OPTIONS.find((opt) => opt.value === value)?.label ??
        value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ')
    );
};