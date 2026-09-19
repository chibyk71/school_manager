/**
 * useAddressCapability — managed Address API operations for a persisted owner.
 *
 * Derives endpoints from controlled owner alias + id.
 * Does not hardcode School/Student/Staff routes beyond the shared /addresses/{owner}/{ownerId} contract.
 * Authorization visibility is supplied by the caller (owner view / owner edit).
 */

import { ref, computed, type Ref } from 'vue';
import axios, { type AxiosError } from 'axios';
import type { Address, AddressFormData } from '@/types/address';

export interface AddressCapabilityOptions {
    owner: string;
    ownerId: string | number | Ref<string | number | null | undefined>;
    /** When false, mutation helpers should be treated as unavailable in UI. */
    canMutate?: boolean | Ref<boolean>;
    canView?: boolean | Ref<boolean>;
}

function resolveId(id: AddressCapabilityOptions['ownerId']): string | null {
    const v = typeof id === 'object' && id !== null && 'value' in id ? id.value : id;
    if (v === null || v === undefined || v === '') return null;
    return String(v);
}

function basePath(owner: string, ownerId: string): string {
    return `/addresses/${encodeURIComponent(owner)}/${encodeURIComponent(ownerId)}`;
}

function extractError(err: unknown): string {
    const ax = err as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;
    if (ax?.response?.data?.errors) {
        const first = Object.values(ax.response.data.errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    return ax?.response?.data?.message || (err as Error)?.message || 'Request failed';
}

export function useAddressCapability(options: AddressCapabilityOptions) {
    const loading = ref(false);
    const error = ref<string | null>(null);
    const addresses = ref<Address[]>([]);

    const ownerIdResolved = computed(() => resolveId(options.ownerId));
    const canView = computed(() => {
        const v = options.canView;
        if (v === undefined) return true;
        return typeof v === 'object' && v !== null && 'value' in v ? Boolean(v.value) : Boolean(v);
    });
    const canMutate = computed(() => {
        const v = options.canMutate;
        if (v === undefined) return true;
        return typeof v === 'object' && v !== null && 'value' in v ? Boolean(v.value) : Boolean(v);
    });

    async function list(): Promise<Address[]> {
        const id = ownerIdResolved.value;
        if (!id || !canView.value) {
            addresses.value = [];
            return [];
        }
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get(basePath(options.owner, id));
            const items = (data?.data ?? data) as Address[];
            addresses.value = Array.isArray(items) ? items : [];
            return addresses.value;
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function create(payload: AddressFormData): Promise<Address> {
        const id = ownerIdResolved.value;
        if (!id) throw new Error('Owner is not persisted');
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post(basePath(options.owner, id), payload);
            const created = (data?.data ?? data) as Address;
            addresses.value = [...addresses.value, created];
            return created;
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function update(addressId: string, payload: Partial<AddressFormData>): Promise<Address> {
        const id = ownerIdResolved.value;
        if (!id) throw new Error('Owner is not persisted');
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.patch(`${basePath(options.owner, id)}/${addressId}`, payload);
            const updated = (data?.data ?? data) as Address;
            addresses.value = addresses.value.map((a) => (a.id === addressId ? updated : a));
            return updated;
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function remove(addressId: string): Promise<void> {
        const id = ownerIdResolved.value;
        if (!id) throw new Error('Owner is not persisted');
        loading.value = true;
        error.value = null;
        try {
            await axios.delete(`${basePath(options.owner, id)}/${addressId}`);
            addresses.value = addresses.value.filter((a) => a.id !== addressId);
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function setPrimary(addressId: string): Promise<Address> {
        const id = ownerIdResolved.value;
        if (!id) throw new Error('Owner is not persisted');
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post(`${basePath(options.owner, id)}/${addressId}/primary`);
            const primary = (data?.data ?? data) as Address;
            addresses.value = addresses.value.map((a) => ({
                ...a,
                is_primary: a.id === addressId,
            }));
            const idx = addresses.value.findIndex((a) => a.id === addressId);
            if (idx >= 0) addresses.value[idx] = { ...addresses.value[idx], ...primary };
            return primary;
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function unsetPrimary(addressId: string): Promise<void> {
        const id = ownerIdResolved.value;
        if (!id) throw new Error('Owner is not persisted');
        loading.value = true;
        error.value = null;
        try {
            await axios.delete(`${basePath(options.owner, id)}/${addressId}/primary`);
            addresses.value = addresses.value.map((a) =>
                a.id === addressId ? { ...a, is_primary: false } : a
            );
        } catch (e) {
            error.value = extractError(e);
            throw e;
        } finally {
            loading.value = false;
        }
    }

    return {
        addresses,
        loading,
        error,
        canView,
        canMutate,
        list,
        create,
        update,
        remove,
        setPrimary,
        unsetPrimary,
    };
}
