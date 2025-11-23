<!-- resources/js/Components/Modals/ResourceDialogWrapper.vue -->
<script setup lang="ts">
/**
 * Central entry-point for **all** modal dialogs.
 *
 * - Renders the **first** modal in `modals.items` (FIFO queue).
 * - Dynamically loads the component from `ModalComponentDirectory`.
 * - Passes the modal payload via `v-bind`.
 * - Emits `@close` → calls `modals.close()` (removes the current modal).
 *
 * @see modals – global reactive modal queue (defined in helpers.ts)
 * @see ModalComponentDirectory – map of modal-id → async component
 */

import { computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { defineAsyncComponent } from 'vue';
import { modals } from '@/helpers';
import { ModalComponentDirectory } from '@/Components/Modals/ModalDirectory';
import { useToast } from 'primevue';

/* ------------------------------------------------------------------ */
/*  TOAST (optional – for internal errors)                           */
/* ------------------------------------------------------------------ */
const toast = useToast();

/* ------------------------------------------------------------------ */
/*  CURRENT MODAL (first item in queue)                               */
/* ------------------------------------------------------------------ */
const current = computed(() => modals.items[0] ?? null);

/* ------------------------------------------------------------------ */
/*  PAYLOAD & ID                                                      */
/* ------------------------------------------------------------------ */
const payload = computed(() => current.value?.data ?? {});
const modalId = computed<string | undefined>(() => current.value?.id);

/* ------------------------------------------------------------------ */
/*  DYNAMIC COMPONENT RESOLUTION                                      */
/* ------------------------------------------------------------------ */
const ModalComponent = computed(() => {
    const id = modalId.value;
    const loader = id ? ModalComponentDirectory[id] : null;
    if (!loader) return undefined;
    return defineAsyncComponent({
        loader,
        loadingComponent: undefined,
        errorComponent: undefined,
        delay: 0,
        timeout: 30000,
        // Optional: show a friendly error in dev if a modal is missing
        onError(error) {
            console.error(`[ResourceDialogWrapper] Failed to load modal "${id}":`, error);
            toast.add({
                severity: 'error',
                summary: 'Modal Error',
                detail: `Modal "${id}" could not be loaded.`,
                life: 5000,
            });
        },
    });
});

/* ------------------------------------------------------------------ */
/*  CLOSE HANDLER                                                     */
/* ------------------------------------------------------------------ */
const closeCurrent = () => {
    modals.close(); // removes the first item (FIFO)
};

/* ------------------------------------------------------------------ */
/*  FORCE RE-CREATION WHEN MODAL CHANGES                              */
/* ------------------------------------------------------------------ */
let renderKey = 0;
watch(
    () => modals.items.length,
    (len, oldLen) => {
        // When the queue becomes empty we bump the key → destroys previous component
        if (len === 0 && oldLen > 0) renderKey++;
    }
);

/* ------------------------------------------------------------------ */
/*  DEBUG / TESTING LIFECYCLE                                         */
/* ------------------------------------------------------------------ */
onMounted(() => {
    console.debug('[ResourceDialogWrapper] mounted – ready for modals');
});
onBeforeUnmount(() => {
    console.debug('[ResourceDialogWrapper] unmounted');
});
</script>

<template>
    <!-- --------------------------------------------------------------
       DYNAMIC MODAL RENDERING
       -------------------------------------------------------------- -->
    <component :is="ModalComponent" v-if="ModalComponent" :key="`${modalId}-${renderKey}`" :id="modalId"
        v-bind="payload" @close="closeCurrent" />
</template>

<style scoped>
/* No extra styling – each modal controls its own layout */
</style>