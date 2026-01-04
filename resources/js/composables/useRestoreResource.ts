// resources/js/composables/useRestoreResource.ts
/**
 * Custom hook for handling soft-deleted resource restoration with confirmation dialog and toast notifications.
 *
 * This hook provides a reusable `restoreResource` function that:
 * - Checks for valid selection (single or bulk)
 * - Displays a PrimeVue confirmation dialog
 * - Performs an Inertia.js POST request to the restore endpoint (supports bulk via { ids })
 * - Shows success/error toasts
 * - Supports custom endpoints and callbacks
 *
 * Features/Problems Solved:
 * - Mirrors useDeleteResource for consistency (same confirm/toast pattern, bulk support)
 * - Handles single and bulk restores uniformly
 * - Integrates seamlessly with your AdvancedDataTable (e.g., for row/bulk actions)
 * - Prevents accidental restores with customizable confirmation
 * - Aligns with Laravel conventions: assumes routes like {resource}.restore (POST with { ids: [...] })
 * - Improves UX in trashed views (e.g., when showTrashed=true)
 *
 * Dependencies:
 * - PrimeVue: useConfirm, useToast
 * - Inertia.js: router
 *
 * Usage Example (in ActionsDropdown or BulkAction handler):
 * const { restoreResource } = useRestoreResource();
 * restoreResource('users', [1, 2, 3], { onSuccess: () => table.refresh() });
 *
 * Backend Assumption:
 * - Route: POST /users/restore (or resource.restore)
 * - Controller: Restore selected models (e.g., Model::whereIn('id', $ids)->restore())
 *
 * @returns {Object} An object containing the `restoreResource` function.
 */
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { router } from '@inertiajs/vue3';

interface RestoreOptions {
    /** Whether to restore from permanent delete context (rarely needed) */
    force?: boolean;
    /** Custom URL endpoint (overrides default route) */
    url?: string;
    /** Callback executed after successful restoration */
    onSuccess?: () => void;
    /** Callback executed if restoration fails */
    onError?: () => void;
}

export function useRestoreResource() {
    const toast = useToast();
    const confirm = useConfirm();

    /**
     * Restores one or more soft-deleted resources with user confirmation.
     *
     * @param resource - The resource name (e.g., 'users', 'posts') used for route naming.
     * @param ids - Array of resource IDs to restore (string or number).
     * @param options - Optional configuration.
     */
    const restoreResource = async (
        resource: string,
        ids: (string | number)[],
        options: RestoreOptions = {}
    ) => {
        const { force = false, url, onSuccess, onError } = options;

        if (!ids || ids.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'Selection Required',
                detail: 'Please select at least one item to restore.',
                life: 3000
            });
            return;
        }

        const isBulk = ids.length > 1;
        const actionText = 'restore';

        confirm.require({
            header: 'Confirm Restoration',
            message: `Are you sure you want to ${actionText} ${isBulk ? `${ids.length} records` : 'this record'}?`,
            icon: 'pi pi-exclamation-triangle',
            acceptProps: {
                label: 'Restore',
                severity: 'success', // Green for positive action
            },
            rejectProps: {
                label: 'Cancel',
                severity: 'secondary',
                outlined: true
            },
            accept: () => {
                const endpoint = url || route(`${resource}.restore`);

                router.post(endpoint, { ids }, {  // Laravel expects { ids: [...] } for bulk
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.add({
                            severity: 'success',
                            summary: 'Success',
                            detail: `${isBulk ? `${ids.length} records` : 'Record'} restored successfully.`,
                            life: 3000
                        });
                        onSuccess?.();
                    },
                    onError: (errors) => {
                        const errorMsg = Object.values(errors).join(' ') || 'An error occurred during restoration.';
                        toast.add({
                            severity: 'error',
                            summary: 'Restore Failed',
                            detail: errorMsg,
                            life: 5000
                        });
                        onError?.();
                    },
                });
            },
            reject: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Cancelled',
                    detail: 'Restoration cancelled.',
                    life: 2000
                });
            }
        });
    };

    return { restoreResource };
}
