<!-- resources/js/Components/Modals/Create/AddressModal.vue -->
<!--
AddressModal.vue v2.0 – Production-Ready Address Create/Edit Modal (Fully Integrated)

Purpose & Problems Solved:
- Central modal for adding or editing addresses across the entire application.
- Seamlessly integrates with your new Modal Module (ModalService + useModal).
- Supports **two distinct submission modes** as designed:
  • **Bundled mode** (default for new addresses in create flows): emits 'saved' event with AddressFormData → parent form collects array.
  • **Direct mode** (for edits or when addressable known): POST/PUT directly via API using useAddress composable.
- Handles both create and edit scenarios with intelligent defaults:
  • Edit: always direct submit (address has id).
  • Create: bundled by default, direct if directSubmit=true + addressableType/Id provided.
- Uses useAddress composable for clean, typed API calls (createAddress / updateAddress).
- Full loading states, PrimeVue toast feedback, validation errors from Laravel/Inertia.
- Accessible, responsive layout with clear header, hint text, and disabled states.
- Emits standard events: 'saved' (bundled), 'updated' (direct success), 'cancel'.
- Graceful fallbacks and error handling (e.g., missing addressable config).
- Type-safe with address.ts (AddressFormData, Address).

Key Changes in v2.0:
- Replaced useModalForm with direct useAddress composable → aligns with new API controller & service.
- Added full direct submit support using createAddress/updateAddress.
- Improved mode detection: directSubmit prop overrides auto-detection.
- Better toast messages and error handling.
- Removed dependency on Inertia form for direct submits (now pure JSON API).
- Cleaner submit handler with explicit success paths.
- Updated header and hint text for clarity.

Fits into the Address Management Module:
- Registered in ModalDirectory.ts as 'address'.
- Opened via useModal().open('address', payload).
- Used by AddressManager.vue for adding extra addresses and editing existing ones.
- Bundled mode: new addresses during main resource creation (e.g., Student create form).
- Direct mode: editing existing addresses or when owner is known (e.g., edit Student).
- Works perfectly with AddressForm.vue (v-model) and useAddress.ts.

Usage Examples:
  // Bundled (new address in create form)
  useModal().open('address', { directSubmit: false });

  // Direct create (rare – when owner known at modal open)
  useModal().open('address', { directSubmit: true, addressableType: 'App\\Models\\Student', addressableId: 123 });

  // Edit existing
  useModal().open('address', { initialData: address, directSubmit: true });
-->

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { Button } from 'primevue';

import AddressForm from '@/Components/Address/AddressForm.vue';
import { useAddress } from '@/composables/useAddress';
import { useModal } from '@/composables/useModal';
import type { Address, AddressFormData } from '@/types/address';
import { delay } from 'lodash';

// ------------------------------------------------------------------
// Props (passed via modal.open('address', payload))
// ------------------------------------------------------------------
const props = defineProps<{
    /** Full address data for edit mode (includes id) */
    initialData?: Address;
    /** Force direct submit mode (overrides auto-detection) */
    directSubmit?: boolean;
    /** Required for direct submit: polymorphic owner */
    addressableType?: string;
    addressableId?: string | number;
}>();

// ------------------------------------------------------------------
// Modal integration
// ------------------------------------------------------------------
const modal = useModal();
const emitter = computed(() => modal.currentItem.value?.emitter ?? null);

// ------------------------------------------------------------------
// State & composables
// ------------------------------------------------------------------
const toast = useToast();
const { createAddress, updateAddress, loading } = useAddress();

const isEditing = computed(() => !!props.initialData?.id);
const headerText = computed(() => isEditing.value ? 'Edit Address' : 'Add New Address');

// Direct submit if: explicitly forced, or editing (has id)
const shouldDirectSubmit = computed(() =>
    props.directSubmit ?? isEditing.value
);

// Local form state (v-model for AddressForm)
const formData = ref<AddressFormData>(
    props.initialData
        ? {
            country_id: props.initialData.country_id,
            state_id: props.initialData.state_id,
            city_id: props.initialData.city_id,
            address_line_1: props.initialData.address_line_1,
            address_line_2: props.initialData.address_line_2,
            landmark: props.initialData.landmark,
            city_text: props.initialData.city_text,
            postal_code: props.initialData.postal_code,
            type: props.initialData.type,
            latitude: props.initialData.latitude,
            longitude: props.initialData.longitude,
            is_primary: props.initialData.is_primary,
        }
        : {
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
        }
);

// ------------------------------------------------------------------
// Submit handlers
// ------------------------------------------------------------------
const handleBundledSubmit = () => {
    emitter.value?.emit('saved', { address: formData.value });
    toast.add({
        severity: 'success',
        summary: 'Ready',
        detail: 'Address will be saved with the main form.',
        life: 3000,
    });
    delay(() => { }, 300)
    handleCancel();
};

const handleDirectSubmit = async () => {
    if (!props.addressableType || props.addressableId === undefined) {
        toast.add({
            severity: 'error',
            summary: 'Configuration Error',
            detail: 'Owner information (type & ID) is required for direct submission.',
            life: 6000,
        });
        return;
    }

    try {
        if (isEditing.value && props.initialData?.id) {
            await updateAddress(
                props.initialData.id,
                formData.value,
                formData.value.is_primary,
                false // toast handled here
            );
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Address updated successfully.',
                life: 3000,
            });
            emitter.value?.emit('updated', { address: { ...formData.value, id: props.initialData.id } });
        } else {
            await createAddress(
                props.addressableType,
                props.addressableId,
                formData.value,
                formData.value.is_primary,
                false
            );
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Address added successfully.',
                life: 3000,
            });
            emitter.value?.emit('updated');
        }
        delay(() => { }, 300)
        handleCancel();
    } catch {
        // Errors already toasted by useAddress
    }
};

const handleSubmit = async () => {
    if (shouldDirectSubmit.value) {
        await handleDirectSubmit();
    } else {
        handleBundledSubmit();
    }
};

const handleCancel = () => {
    emitter.value?.emit('closed');
};
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            {{ headerText }}
        </h2>

        <AddressForm v-model="formData" :disabled="loading" />

        <div
            class="flex flex-col sm:flex-row sm:justify-between gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div v-if="!shouldDirectSubmit"
                class="text-sm text-gray-600 dark:text-gray-400 text-center sm:text-left order-2 sm:order-1">
                The address will be saved together with the main form.
            </div>

            <div class="flex justify-end gap-3 order-1 sm:order-2">
                <Button label="Cancel" severity="secondary" :disabled="loading" @click="handleCancel" />
                <Button :label="isEditing ? 'Update Address' : 'Add Address'" icon="pi pi-check" :loading="loading"
                    @click="handleSubmit" />
            </div>
        </div>
    </div>
</template>

<style scoped lang="postcss">
:deep(.p-dialog-content) {
    @apply overflow-y-auto;
}
</style>
