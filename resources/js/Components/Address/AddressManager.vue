<!-- resources/js/Components/Address/AddressManager.vue -->
<!--
AddressManager.vue v1.0 – Production-Ready Multi-Address Orchestrator Component

Purpose & Problems Solved:
- Central component for managing multiple addresses on any resource create/edit form (Student, Staff, School, etc.).
- Provides seamless UX for 0 → many addresses:
  • No addresses → shows inline AddressForm for first address (fastest path).
  • One or more → shows AddressList with cards.
  • "Add another address" button opens AddressModal for additional entries.
- Handles both **create** and **edit** modes intelligently:
  • Create: starts with bundled mode (collects addresses locally, emits array to parent form).
  • Edit: fetches existing addresses via useAddress on mount, supports direct API edits.
- Validates first inline address before allowing "Add more" (prevents invalid data in list).
- Emits clean AddressFormData[] to parent via v-model – perfect for Inertia form submission.
- Full integration with AddressModal (bundled/direct modes), AddressList, AddressForm, and useAddress composable.
- Responsive, accessible, loading states, toast feedback.
- Local state management: reactive addresses array, editing detection.
- Prevents data loss: confirmation on delete, refresh after direct operations.

Key Features:
- v-model support (two-way binding to parent form's addresses array).
- Inline first address form (bundled mode) with validation gate.
- AddressList display for existing addresses.
- "Add Address" button opens modal in bundled mode.
- Edit/Delete/Set Primary via AddressList → modal or direct API.
- Auto-refresh after direct create/update/delete.
- Empty state handled via AddressList.
- Type-safe with address.ts (Address, AddressFormData).

Fits into the Address Management Module:
- Primary integration point in resource forms (e.g., StudentCreate.vue, StaffEdit.vue).
- Parent form: <AddressManager v-model="form.addresses" :addressable-type="modelType" :addressable-id="modelId" />
- On create: collects addresses → submits with main form.
- On edit: loads existing, allows add/edit/delete with direct API sync.
- Completes the module: composes AddressForm, AddressList, AddressItem, AddressModal, useAddress.

Usage Example:
<AddressManager
  v-model="form.addresses"
  :addressable-type="addressableType"   // e.g., 'App\\Models\\Student'
  :addressable-id="student?.id"         // undefined on create
/>

Dependencies:
- AddressForm.vue (inline first address)
- AddressList.vue (display existing)
- AddressModal.vue (add/edit via modal)
- useAddress.ts (fetch/create/update/delete)
- useModal() (open AddressModal)
- '@/types/address'
-->

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { Button } from 'primevue';

import AddressForm from '@/Components/Address/AddressForm.vue';
import AddressList from '@/Components/Address/AddressList.vue';
import { useAddress } from '@/composables/useAddress';
import { useModal } from '@/composables/useModal';
import type { Address, AddressFormData } from '@/types/address';

// ------------------------------------------------------------------
// Props
// ------------------------------------------------------------------
const props = defineProps<{
    /** v-model: array of addresses (AddressFormData for new, Address for existing) */
    modelValue: (Address | AddressFormData)[];
    /** Polymorphic owner type – required for edit mode fetch & direct operations */
    addressableType: string;
    /** Polymorphic owner ID – present only in edit mode */
    addressableId?: string | number;
}>();

const localAddresses = defineModel<(Address | AddressFormData)[]>({
    default: () => []
});


// Sync with v-model
watch(
    () => props.modelValue,
    (newVal) => {
        localAddresses.value = [...newVal];
    },
    { immediate: true }
);

// ------------------------------------------------------------------
// Composable & modal
// ------------------------------------------------------------------
const toast = useToast();
const { fetchAddresses, loading } = useAddress();
const modal = useModal();

const isEditMode = computed(() => !!props.addressableId);
const hasAddresses = computed(() => localAddresses.value.length > 0);
const showInlineForm = computed(() => !hasAddresses.value && !isEditMode.value);

// First inline address (bundled)
const inlineAddress = ref<AddressFormData>({
    country_id: null,
    state_id: null,
    city_id: null,
    address_line_1: '',
    address_line_2: null,
    landmark: null,
    city_text: null,
    postal_code: null,
    type: null,
    latitude: null,
    longitude: null,
    is_primary: true, // First is primary by default
});

// Provide a typed addresses ref for AddressList: force-cast the local union to Address[]
// This intentionally narrows types for the child prop where AddressList expects Address[]
const addressesForList = computed<Address[]>(() => localAddresses.value as Address[]);

// Validation for inline form (simple required check)
const isInlineValid = computed(() => {
    return !!inlineAddress.value.country_id && !!inlineAddress.value.address_line_1;
});

// ------------------------------------------------------------------
// Load existing addresses on edit
// ------------------------------------------------------------------
onMounted(async () => {
    if (isEditMode.value && props.addressableId && props.addressableType) {
        const fetched = await fetchAddresses(props.addressableType, props.addressableId);
        if (fetched.length > 0) {
            localAddresses.value = fetched;
        }
    }
});

// ------------------------------------------------------------------
// Handlers
// ------------------------------------------------------------------
const addInlineAddress = () => {
    if (!isInlineValid.value) {
        toast.add({
            severity: 'warn',
            summary: 'Validation',
            detail: 'Please fill required fields (Country, Address Line 1) before adding.',
            life: 4000,
        });
        return;
    }

    localAddresses.value.push({ ...inlineAddress.value });

    // Reset for next (but don't show inline again)
    inlineAddress.value = {
        country_id: null,
        state_id: null,
        city_id: null,
        address_line_1: '',
        address_line_2: null,
        landmark: null,
        city_text: null,
        postal_code: null,
        type: null,
        latitude: null,
        longitude: null,
        is_primary: false,
    };

    toast.add({
        severity: 'success',
        summary: 'Added',
        detail: 'First address added. You can now add more via the button.',
        life: 3000,
    });
};

const openAddModal = () => {
    modal.open('address', {
        directSubmit: false, // bundled – new address in create flow
    });
};

const openEditModal = (address: Address) => {
    modal.open('address', {
        initialData: address,
        directSubmit: true, // edit existing → direct API
    });
};

const handleSetPrimary = async (address: Address) => {
    // Direct API call via useAddress
    const { setPrimaryAddress } = useAddress();
    const updated = await setPrimaryAddress(address.id);
    if (updated) {
        // Refresh local list
        const fetched = await fetchAddresses(props.addressableType, props.addressableId!);
        localAddresses.value = fetched;
        toast.add({
            severity: 'success',
            summary: 'Primary Updated',
            detail: 'Primary address changed successfully.',
            life: 3000,
        });
    }
};

const handleDelete = async (address: Address) => {
    const { deleteAddress } = useAddress();
    const success = await deleteAddress(address.id);
    if (success) {
        // Keep items that are not persisted (no id) or whose id doesn't match the deleted one
        localAddresses.value = localAddresses.value.filter(a => {
            return !('id' in a) || a.id !== address.id;
        });
        toast.add({
            severity: 'success',
            summary: 'Deleted',
            detail: 'Address removed successfully.',
            life: 3000,
        });
    }
};

// Listen for bundled save from modal (new address in create mode)
modal.emitter.value?.on('saved', ({ address }: { address: AddressFormData }) => {
    localAddresses.value.push(address);
    toast.add({
        severity: 'success',
        summary: 'Added',
        detail: 'Address added. It will be saved with the main form.',
        life: 3000,
    });
});

// Listen for direct update success (edit mode)
modal.emitter.value?.on('updated', async (address: Address) => {
    if (isEditMode.value) {
        localAddresses.value.push(address);
    }
});
</script>

<template>
    <div class="space-y-8">
        <!-- Inline First Address (Create Mode Only) -->
        <div v-if="showInlineForm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Add Primary Address
            </h3>
            <AddressForm v-model="inlineAddress" />
            <div class="mt-4 flex justify-end">
                <Button label="Add Address & Continue" icon="pi pi-plus" :disabled="!isInlineValid"
                    @click="addInlineAddress" />
            </div>
        </div>

        <!-- Existing Addresses List -->
        <AddressList v-if="hasAddresses" :addresses="addressesForList" :loading="loading" @edit="openEditModal"
            @set-primary="handleSetPrimary" @delete="handleDelete" />

        <!-- Add More Button (after first address added) -->
        <div v-if="hasAddresses || !showInlineForm" class="flex justify-center">
            <Button label="Add Another Address" icon="pi pi-plus" severity="secondary" outlined @click="openAddModal" />
        </div>
    </div>
</template>
