// resources/js/composables/useAddress.ts
/**
 * useAddress.ts v1.0 – Centralised Composable for Address API Interactions
 *
 * Purpose & Problems Solved:
 * - Single source of truth for all address-related HTTP operations across the frontend.
 * - Eliminates duplicated Axios/Inertia calls in AddressModal.vue, AddressManager.vue, DataTables, etc.
 * - Provides consistent loading states, error handling, and PrimeVue toast feedback.
 * - Handles both bundled (emit saved event) and direct (API submit) modes used in AddressModal.
 * - Supports polymorphic fetching (by addressable_type + addressable_id) – critical for editing existing resources.
 * - Returns fresh addresses with relations loaded (country/state/city) for immediate display.
 * - Type-safe with address.ts definitions – no "any" leakage.
 * - Optimistic updates possible in future (e.g., add to local list before server response).
 * - Graceful error handling: validation errors shown via toast, network errors logged + toasted.
 *
 * Key Features:
 * - fetchAddresses(addressableType, addressableId): GET /addresses with filters
 * - createAddress(addressableType, addressableId, data, isPrimary): POST /addresses
 * - updateAddress(addressId, data, makePrimary): PUT /addresses/{id}
 * - deleteAddress(addressId): DELETE /addresses/{id}
 * - setPrimaryAddress(addressId): calls update with is_primary=true
 * - All functions return Promise<Address | Address[]> and accept optional toast control
 *
 * Fits into the Address Management Module:
 * - Primary consumer: AddressManager.vue (load existing addresses on edit, refresh after CRUD)
 * - Used by AddressModal.vue in directSubmit mode (bypasses modal emit, calls API directly)
 * - Can be used in custom DataTables or admin address lists
 * - Works seamlessly with AddressController endpoints and AddressService backend logic
 * - Integrates with PrimeVue useToast for consistent user feedback
 *
 * Usage Examples:
 *   const { fetchAddresses, createAddress, loading, error } = useAddress();
 *
 *   // Load addresses for a student
 *   const addresses = await fetchAddresses('App\\Models\\Student', studentId);
 *
 *   // Create new address (direct submit from modal)
 *   await createAddress('App\\Models\\Student', studentId, formData, true);
 *
 * Dependencies:
 * - axios (or @inertiajs/inertia if preferred – axios used here for JSON API)
 * - primevue/usetoast
 * - '@/types/address' (Address, AddressFormData)
 */

import { readonly, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import type { Address, AddressFormData } from '@/types/address';

export function useAddress() {
    const toast = useToast();

    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Fetch all addresses for a polymorphic owner.
     */
    const fetchAddresses = async (
        addressableType: string,
        addressableId: string | number
    ): Promise<Address[]> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/api/addresses', {
                params: {
                    addressable_type: addressableType,
                    addressable_id: addressableId,
                    with_trashed: false, // optional: set true if needed
                },
            });

            return response.data.data as Address[];
        } catch (err: any) {
            const message =
                err.response?.data?.message ||
                'Failed to load addresses. Please try again.';

            error.value = message;
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: message,
                life: 5000,
            });

            console.error('[useAddress] fetchAddresses failed:', err);
            return [];
        } finally {
            loading.value = false;
        }
    };

    /**
     * Create a new address (direct API submit).
     */
    const createAddress = async (
        addressableType: string,
        addressableId: string | number,
        data: AddressFormData,
        isPrimary = false,
        showToast = true
    ): Promise<Address | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/addresses', {
                addressable_type: addressableType,
                addressable_id: addressableId,
                ...data,
                is_primary: isPrimary,
            });

            const address = response.data.data as Address;

            if (showToast) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Address added successfully.',
                    life: 3000,
                });
            }

            return address;
        } catch (err: any) {
            const message =
                err.response?.data?.message ||
                    err.response?.data?.errors
                    ? Object.values(err.response.data.errors).flat().join(' ')
                    : 'Failed to create address.';

            error.value = message;

            if (showToast) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: message,
                    life: 6000,
                });
            }

            console.error('[useAddress] createAddress failed:', err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update an existing address.
     */
    const updateAddress = async (
        addressId: string,
        data: Partial<AddressFormData>,
        makePrimary = false,
        showToast = true
    ): Promise<Address | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.put(`/api/addresses/${addressId}`, {
                ...data,
                is_primary: makePrimary,
            });

            const address = response.data.data as Address;

            if (showToast) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Address updated successfully.',
                    life: 3000,
                });
            }

            return address;
        } catch (err: any) {
            const message =
                err.response?.data?.message ||
                    err.response?.data?.errors
                    ? Object.values(err.response.data.errors).flat().join(' ')
                    : 'Failed to update address.';

            error.value = message;

            if (showToast) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: message,
                    life: 6000,
                });
            }

            console.error('[useAddress] updateAddress failed:', err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Soft delete an address.
     */
    const deleteAddress = async (
        addressId: string,
        showToast = true
    ): Promise<boolean> => {
        loading.value = true;
        error.value = null;

        try {
            await axios.delete(`/api/addresses/${addressId}`);

            if (showToast) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Address deleted successfully.',
                    life: 3000,
                });
            }

            return true;
        } catch (err: any) {
            const message =
                err.response?.data?.message || 'Failed to delete address.';

            error.value = message;

            if (showToast) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: message,
                    life: 5000,
                });
            }

            console.error('[useAddress] deleteAddress failed:', err);
            return false;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Set an address as primary (convenience wrapper).
     */
    const setPrimaryAddress = async (
        addressId: string,
        showToast = true
    ): Promise<Address | null> => {
        return updateAddress(
            addressId,
            { is_primary: true },
            true,
            showToast
        );
    };

    return {
        // State
        loading: readonly(loading),
        error: readonly(error),

        // Actions
        fetchAddresses,
        createAddress,
        updateAddress,
        deleteAddress,
        setPrimaryAddress,
    };
}