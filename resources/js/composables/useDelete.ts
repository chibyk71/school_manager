import { useConfirm, useToast } from "primevue";
import { router } from "@inertiajs/vue3";

interface DeleteOptions {
    force?: boolean;
    url?: string;
    onSuccess?: () => void;
    onError?: (e:string) => void;
}

/**
 * Custom hook for handling resource deletion with confirmation dialog and toast notifications.
 *
 * This hook provides a reusable `deleteResource` function that:
 * - Checks for valid selection
 * - Displays a PrimeVue confirmation dialog
 * - Performs an Inertia.js DELETE request (with optional force-delete)
 * - Shows success/error toasts
 * - Supports bulk deletion and custom endpoints
 *
 * @returns {deleteResource: (resource: string, ids: (string | number)[], options?: DeleteOptions) => Promise<void>;} An object containing the `deleteResource` function.
 */
export function useDeleteResource(): {
    deleteResource: (resource: string, ids: (string | number)[], options?: DeleteOptions) => Promise<void>;
} {
    // PrimeVue toast service for displaying notifications
    const toast = useToast();

    // PrimeVue confirmation service for showing delete confirmation dialogs
    const confirm = useConfirm();

    /**
     * Deletes one or more resources with user confirmation.
     *
     * @param resource - The resource name (e.g., 'users', 'posts') used for route naming.
     * @param ids - Array of resource IDs to delete (string or number).
     * @param options - Optional configuration for the deletion process.
     * @param options.force - Whether to perform a permanent (force) delete. Defaults to false.
     * @param options.url - Custom URL endpoint (overrides the default route).
     * @param options.onSuccess - Callback executed after successful deletion.
     * @param options.onError - Callback executed if deletion fails.
     */
    const deleteResource = async ( resource: string, ids: (string | number)[], options: DeleteOptions = {}) => {
        const { force = false, url, onSuccess, onError } = options;

        // Early return if no IDs are provided
        if (!ids || ids.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'Selection Required',
                detail: 'Please select at least one item to delete.',
                life: 3000
            });
            return;
        }

        // Determine if this is a bulk operation
        const isBulk = ids.length > 1;
        const actionText = force ? 'permanently delete' : 'delete';

        // Show confirmation dialog using PrimeVue's useConfirm
        confirm.require({
            header: 'Confirm Deletion',
            message: `Are you sure you want to ${actionText} ${isBulk ? `${ids.length} records` : 'this record'}?`,
            icon: 'pi pi-exclamation-triangle',
            acceptProps: {
                label: force ? 'Force Delete' : 'Delete',
                severity: 'danger',
            },
            rejectProps: {
                label: 'Cancel',
                severity: 'secondary',
                outlined: true
            },
            // Accept handler: perform the deletion
            accept: () => {
                // Determine the endpoint URL
                // If custom URL provided, use it; otherwise generate via Ziggy's route() helper
                // Assumes Ziggy is set up for Laravel named routes (e.g., users.destroy, users.forceDelete)
                const endpoint = url || route(`${resource}.${force ? 'forceDelete' : 'destroy'}`);

                // Perform Inertia.js DELETE request with payload
                router.delete(endpoint, {
                    data: { ids }, // Send IDs as payload (Laravel expects this for bulk delete)
                    preserveScroll: true, // Keep scroll position after redirect
                    // Success handler
                    onSuccess: (page) => {
                        toast.add({
                            severity: 'success',
                            summary: 'Success',
                            detail: `Resource(s) ${force ? 'permanently' : ''} deleted successfully.`,
                            life: 3000
                        });
                        if (onSuccess) onSuccess();
                    },
                    // Error handler
                    onError: (errors) => {
                        // Extract error message from Inertia errors or fallback
                        const errorMsg = Object.values(errors).join(' ') || 'An error occurred during deletion.';
                        toast.add({
                            severity: 'error',
                            summary: 'Delete Failed',
                            detail: errorMsg,
                            life: 5000
                        });
                        if (onError) onError(errorMsg);
                    },
                });
            },
            // Reject handler: show cancellation toast
            reject: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Cancelled',
                    detail: 'Deletion cancelled.',
                    life: 2000
                });
            }
        });
    };

    // Return the public API
    return { deleteResource };
}
