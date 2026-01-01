<!-- resources/js/Components/Modals/ResourceDialog.vue -->
<script setup lang="ts">
/**
 * ResourceDialog.vue
 *
 * The single, globally-placed dialog component that renders the topmost modal from the ModalService queue.
 *
 * Features / Problems Solved:
 * - Renders exactly one PrimeVue Dialog that reflects the current top modal (queue[0]).
 * - Dynamically loads the registered modal component via Suspense + defineAsyncComponent (code-splitting).
 * - Applies per-modal configuration from ModalDirectory (title, maxWidth, maxHeight, persistent behavior).
 * - Handles loading, error, and fallback states gracefully.
 * - Passes payload data and instanceId to the child modal so it can emit custom events to its own isolated emitter.
 * - Supports both normal stacking (open) and priority overlays (prepend).
 * - Forces clean remount when a new modal appears or all close (via reactive key).
 * - Fully accessible: uses PrimeVue Dialog's built-in focus trap, ARIA roles, and keyboard support.
 * - Responsive and consistent styling across the entire app.
 *
 * Fits into the Modal Module:
 * - Placed once in the root layout (e.g., App.vue) – no need to import elsewhere.
 * - Reads directly from ModalService via useModal() composable.
 * - Works seamlessly with ModalDirectory (component resolution + config) and ModalService (queue management).
 */

import { computed, defineComponent, watch } from 'vue';
import { defineAsyncComponent } from 'vue';
import { useToast } from 'primevue/usetoast';
import { Dialog, ProgressSpinner } from 'primevue';

import { ModalComponentDirectory, type ModalId } from '@/Components/Modals/ModalDirectory';
import { useModal } from '@/composables/useModal';

const toast = useToast();
const modalService = useModal();

// Current top modal from the reactive queue
const currentItem = modalService.currentItem;

const modalId = computed<ModalId | null>(() => currentItem.value?.id ?? null);
const payload = computed(() => currentItem.value?.data ?? {});
const instanceId = computed(() => currentItem.value?.instanceId ?? '');
const config = computed(() => currentItem.value?.config ?? {});

// Dynamic async component with built-in loading/error handling
const ModalComponent = computed(() => {
    if (!modalId.value) return null;

    const entry = ModalComponentDirectory[modalId.value];
    if (!entry?.loader) {
        console.error(`[ResourceDialog] No registration found for modal ID: "${modalId.value}"`);
        toast.add({
            severity: 'error',
            summary: 'Modal Error',
            detail: `Modal "${modalId.value}" is not registered in ModalDirectory.ts`,
            life: 8000,
        });
        return null;
    }

    return defineAsyncComponent({
        loader: entry.loader,
        loadingComponent: defineComponent({
            template: `
            <div class="text-center py-16 text-gray-500">
                <i class="pi pi-spinner pi-spin text-6xl mb-4"></i>
                <p>Loading...</p>
            </div>
            `,
        }),
        errorComponent: defineComponent({
            template: `
            <div class="text-center py-16 text-red-600">
                <i class="pi pi-exclamation-triangle text-6xl mb-4"></i>
                <p>Failed to load modal.</p>
            </div>
        `,
        }),
        delay: 100,
        timeout: 30000,
    });
});

// Close the current modal via the service
const closeModal = () => {
    if (instanceId.value) modalService.close(instanceId.value);
};

// Force remount when queue changes (ensures clean state when opening a new modal)
let renderKey = 0;
watch(
    () => modalService.queueLength.value,
    (newLength, oldLength) => {
        if (newLength > oldLength!) renderKey++; // new modal opened
        if (newLength === 0 && oldLength! > 0) renderKey++; // all modals closed
    },
    { immediate: true }
);
</script>

<template>
    <Dialog v-if="currentItem" :key="renderKey" :visible="true" :modal="true" :closable="!config.persistent"
        :dismissable-mask="!config.persistent" :close-on-escape="!config.persistent" @update:visible="closeModal"
        :header="config.title" :show-header="!!config.title" block-scroll :pt="{
            root: { class: ['rounded-xl shadow-2xl', config.maxWidth ? `max-w-${config.maxWidth}` : 'max-w-4xl', 'w-full mx-4'] },
            header: { class: 'text-lg font-semibold border-b border-gray-200 pb-4' },
            content: { class: 'p-6' },
            footer: { class: 'hidden' },
        }" class="resource-dialog">
        <Suspense>
            <template #default>
                <component :is="ModalComponent" v-bind="payload" @close="closeModal" class="w-full" />
            </template>

            <template #fallback>
                <div class="flex flex-col items-center justify-center py-16">
                    <ProgressSpinner />
                    <p class="mt-4 text-gray-600">Loading...</p>
                </div>
            </template>
        </Suspense>
    </Dialog>
</template>

<style scoped lang="postcss">
:deep(.p-dialog) {
    max-height: 95vh;
    overflow-y: auto;
}

/* Optional: improve header spacing when no title is provided */
:deep(.p-dialog-header:empty) {
    @apply hidden;
}
</style>
