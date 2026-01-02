<!-- resources/js/Components/Address/AddressList.vue -->
<!--
AddressList.vue v1.0 – Reusable Address List Component with Empty State

Purpose & Problems Solved:
- Renders a responsive grid of AddressItem cards from an array of addresses.
- Provides a clean, user-friendly empty state when no addresses exist.
- Centralises list display logic for consistency across the app (used by AddressManager.vue).
- Handles loading skeleton state for better UX during async fetches.
- Emits forwarded events from AddressItem (edit, set-primary, delete) – keeps parent in control.
- Responsive Tailwind grid: 1 column on mobile, 2 on tablet, 3 on desktop.
- Accessible: proper heading, ARIA live region for dynamic updates.
- No drag-reorder implemented (optional feature deferred – addresses rarely need ordering).
- No bulk actions (rare for addresses) – kept simple and focused.
- Type-safe with Address type from address.ts.

Fits into the Address Management Module:
- Primary consumer: AddressManager.vue (displays existing addresses on edit/view).
- Receives addresses[] from parent (fetched via useAddress composable).
- Forwards all user actions to parent for handling (modal open, API calls, refresh).
- Empty state encourages adding first address (used in create mode).
- Works seamlessly with AddressItem.vue (child) and future AddressManager.vue (parent).

Usage Example:
<AddressList
  :addresses="addresses"
  :loading="loading"
  @edit="openEditModal"
  @set-primary="handleSetPrimary"
  @delete="handleDelete"
/>

Dependencies:
- AddressItem.vue (renders individual card)
- '@/types/address' (Address type)
- PrimeVue: Skeleton (for loading state)
-->

<script setup lang="ts">
import { computed } from 'vue';
import AddressItem from '@/Components/Address/AddressItem.vue';
import type { Address } from '@/types/address';

// ------------------------------------------------------------------
// Props
// ------------------------------------------------------------------
const props = defineProps<{
    addresses: Address[];
    /** Show skeleton loaders while fetching */
    loading?: boolean;
}>();

// ------------------------------------------------------------------
// Emits – forward child events
// ------------------------------------------------------------------
const emit = defineEmits<{
    edit: [address: Address];
    'set-primary': [address: Address];
    delete: [address: Address];
}>();

// ------------------------------------------------------------------
// Computed
// ------------------------------------------------------------------
const hasAddresses = computed(() => props.addresses.length > 0);
</script>

<template>
    <div class="space-y-6">
        <!-- Heading -->
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Addresses
            <span v-if="!loading" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                ({{ addresses.length }})
            </span>
        </h3>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="n in 3" :key="n"
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <Skeleton height="1.5rem" width="30%" class="mb-3" />
                <Skeleton height="1rem" class="mb-2" />
                <Skeleton height="1rem" class="mb-2" />
                <Skeleton height="1rem" width="80%" />
                <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <Skeleton width="2rem" height="2rem" border-radius="50%" />
                    <Skeleton width="2rem" height="2rem" border-radius="50%" />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!hasAddresses"
            class="text-center py-12 bg-gray-50 dark:bg-gray-800/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
            <i class="pi pi-home text-5xl text-gray-400 dark:text-gray-600 mb-4"></i>
            <p class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">
                No addresses added yet
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Add the first address to get started.
            </p>
        </div>

        <!-- Address Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" role="list" aria-live="polite">
            <AddressItem v-for="address in addresses" :key="address.id" :address="address" @edit="emit('edit', $event)"
                @set-primary="emit('set-primary', $event)" @delete="emit('delete', $event)" />
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Optional: subtle animation on card entrance */
.grid>div {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
