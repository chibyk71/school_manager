// src/composables/useModal.ts
/**
 * useModal.ts
 *
 * Lightweight, type-safe composable that exposes the global ModalService to any Vue component.
 *
 * Features / Problems Solved:
 * - Provides reactive access to the current modal state (currentItem, queueLength, queueItems).
 * - Exposes the top modal's emitter for quick event listening without needing the full instance.
 * - Direct access to open(), prepend(), close(), closeAll() with proper binding.
 * - Adds a convenient closeCurrent() helper – the most common action after a modal finishes.
 * - Throws a clear error if ModalPlugin is not installed (fails fast during development).
 * - Keeps the API small and intuitive while staying fully typed.
 *
 * Fits into the Modal Module:
 * - Preferred public API for components to interact with modals.
 * - Used instead of injecting ModalService directly – gives reactive state + helpers.
 * - Works seamlessly with ResourceDialog.vue (which reads service.currentItem) and ModalService (open/prepend).
 * - Enables patterns like:
 *   • Open a form → await result → refresh table
 *   • Prepend a confirmation/alert on top of an existing modal
 *   • Listen to custom events from the top modal without storing the instance
 */

import { computed, inject } from 'vue';
import { MODAL_SERVICE_KEY, ModalService } from '@/Components/Modals/ModalService';

export function useModal() {
    const service = inject<ModalService>(MODAL_SERVICE_KEY);

    if (!service) {
        throw new Error(
            'useModal() called without ModalPlugin installed. ' +
            'Ensure app.use(ModalPlugin) is called in main.ts'
        );
    }

    // Reactive state reflecting the current queue
    const currentItem = computed(() => service.currentItem);
    const queueLength = computed(() => service.queueLength);
    const queueItems = computed(() => service.queueItems);

    // Quick access to the emitter of the topmost modal (very useful for one-off listeners)
    const emitter = computed(() => service.currentItem?.emitter ?? null);

    return {
        /**
         * Reactive reference to the currently visible (topmost) modal item.
         * Contains id, data, config, instanceId, and emitter.
         * Becomes null when no modals are open.
         */
        currentItem,

        /**
         * Reactive mitt emitter belonging to the topmost modal.
         * Useful for quick event listening, e.g.:
         *   useModal().emitter?.on('saved', refreshTable)
         * Returns null when no modal is open.
         */
        emitter,

        /**
         * Reactive number representing how many modals are currently in the queue.
         * Useful for showing indicators or disabling interactions when modals are open.
         */
        queueLength,

        /**
         * Reactive readonly array of all queued modal items (in render order).
         * Primarily for debugging or advanced UI (e.g., modal stack indicator).
         */
        queueItems,

        /**
         * Opens a modal and adds it to the end of the queue (standard stacking behavior).
         * Returns a ModalInstance handle for event listening, data updates, and Promise support.
         *
         * @param id - Registered modal identifier
         * @param data - Payload passed to the modal component
         * @param options - { async?: boolean, preventDuplicates?: boolean }
         */
        open: service.open.bind(service),

        /**
         * Opens a modal and adds it to the beginning of the queue (priority overlay).
         * Ideal for alerts, confirmations, or loading indicators that must appear on top.
         *
         * @param id - Registered modal identifier
         * @param data - Payload passed to the modal component
         * @param options - { async?: boolean, preventDuplicates?: boolean }
         */
        prepend: service.prepend.bind(service),

        /**
         * Closes a specific modal by its instanceId.
         * Typically used internally via the returned ModalInstance.close().
         *
         * @param instanceId - Unique ID of the modal to close
         * @param result - Optional value to resolve an async Promise
         */
        close: service.close.bind(service),

        /**
         * Closes all open modals at once.
         * Useful for logout, navigation guards, or global reset scenarios.
         */
        closeAll: service.closeAll.bind(service),

        /**
         * Convenience helper: closes only the currently visible (topmost) modal.
         * This is the most common close action in UI components.
         * Safely no-ops if no modal is open.
         */
        closeCurrent: () => {
            if (service.currentItem) {
                service.close(service.currentItem.instanceId);
            }
        },
    };
}
