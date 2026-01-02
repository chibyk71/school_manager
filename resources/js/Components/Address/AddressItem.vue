<!-- resources/js/Components/Address/AddressItem.vue -->
<!--
AddressItem.vue v1.0 – Single Address Display Card with Actions

Purpose & Problems Solved:
- Renders one address in a clean, readable card format for use in lists (AddressList.vue).
- Displays the human-readable formatted address (from backend accessor).
- Highlights primary address with badge and distinct styling.
- Provides intuitive action buttons: Edit, Set as Primary (if not primary), Delete.
- Emits typed events for parent handling (AddressList.vue / AddressManager.vue):
  • edit(address) – open modal for editing
  • set-primary(address) – make this the primary address
  • delete(address) – soft delete with confirmation
- Fully accessible: ARIA labels, keyboard-friendly buttons, proper contrast.
- Responsive: stacks vertically on mobile, compact on small screens.
- Consistent with PrimeVue + Tailwind design system (buttons, badges, icons).
- Handles loading/disabled states via props (e.g., during delete operation).
- Uses address.ts types for type safety.

Fits into the Address Management Module:
- Used exclusively by AddressList.vue as the repeating item.
- Works with AddressManager.vue flow: edit → AddressModal.vue, delete → confirmation + refresh.
- Primary badge and "Set as Primary" logic aligns with HasAddress trait enforcement.
- Delete emits event → parent calls useAddress.deleteAddress() → refreshes list.
- Edit emits full Address object → modal pre-fills via v-model.

Usage Example:
<AddressItem
  :address="address"
  :is-processing="processingId === address.id"
  @edit="openEditModal"
  @set-primary="handleSetPrimary"
  @delete="handleDelete"
/>

Dependencies:
- PrimeVue: Button, Badge, ConfirmPopup (optional via parent)
- '@/types/address' (Address type)
- Heroicons or PrimeIcons for edit/delete actions
-->

<script setup lang="ts">
import { computed } from 'vue';
import { Button, Badge } from 'primevue';
import type { Address } from '@/types/address';
import { getAddressTypeLabel } from '@/types/address';

// ------------------------------------------------------------------
// Props
// ------------------------------------------------------------------
const props = defineProps<{
    address: Address;
    /** Optional: disable actions or show loading state */
    isProcessing?: boolean;
}>();

// ------------------------------------------------------------------
// Emits
// ------------------------------------------------------------------
const emit = defineEmits<{
    edit: [address: Address];
    'set-primary': [address: Address];
    delete: [address: Address];
}>();

// ------------------------------------------------------------------
// Computed
// ------------------------------------------------------------------
const isPrimary = computed(() => props.address.is_primary);

const typeLabel = computed(() => getAddressTypeLabel(props.address.type));
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-shadow"
        :class="{ 'ring-2 ring-primary-500 ring-offset-2': isPrimary }">
        <!-- Header: Primary Badge + Type -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <Badge v-if="isPrimary" value="Primary" severity="success" class="text-xs font-medium" />
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ typeLabel }}
                </span>
            </div>
        </div>

        <!-- Formatted Address -->
        <div class="text-base text-gray-900 dark:text-gray-100 leading-relaxed mb-4">
            {{ address.formatted || 'No address details available' }}
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
            <!-- Edit -->
            <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" :disabled="isProcessing"
                @click="emit('edit', address)" aria-label="Edit address" v-tooltip.top="'Edit this address'" />

            <!-- Set as Primary (only if not already primary) -->
            <Button v-if="!isPrimary" label="Set Primary" severity="info" outlined size="small" :disabled="isProcessing"
                @click="emit('set-primary', address)" aria-label="Set as primary address"
                v-tooltip.top="'Make this the primary address'" />

            <!-- Delete -->
            <Button icon="pi pi-trash" severity="danger" text rounded size="small" :disabled="isProcessing"
                @click="emit('delete', address)" aria-label="Delete address" v-tooltip.top="'Delete this address'" />
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Ensure consistent button spacing on small screens */
@media (max-width: 640px) {
    .flex.justify-end.gap-2 {
        @apply flex-wrap;
    }
}
</style>
