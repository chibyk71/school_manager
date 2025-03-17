import axios from "axios";
import { useConfirm, useToast } from "primevue";
import { router } from '@inertiajs/vue3';

export const getClass = (cls: string) => {
    const documentStyle = getComputedStyle(document.documentElement);
    return documentStyle.getPropertyValue(cls);;
};

export function useDeleteResource() {
    const toast = useToast();
    const confirm = useConfirm();

    /**
     * Delete a resource from the database.
     *
     * @param {string} resource - The name of the resource.
     * @param {Array<number|string>} ids - The IDs of the resources to delete.
     * @param {string} [url] - The URL to make the request to. Defaults to `/api/${resource}/${id}`.
     * @returns {Promise<void>}
     */
    const deleteResource = async (resource: string, ids: Array<number | string>, url?: string): Promise<void> => {
        if (!ids || ids.length === 0) {
            toast.add({ severity: 'warn', summary: 'Warning', detail: 'No resource IDs provided.', life: 3000 });
            return;
        }

        confirm.require({
            message: ids.length > 1 
                ? `Are you sure you want to delete ${ids.length} records?` 
                : 'Do you want to delete this record?',
            header: "Delete Confirmation",
            icon: 'pi pi-info-circle',
            rejectLabel: 'Cancel',
            rejectProps: {
                label: 'Cancel',
                severity: 'secondary',
                outlined: true
            },
            acceptProps: {
                label: 'Delete',
                severity: 'danger'
            },
            accept: async () => {
                try {
                    await axios.delete(url || route(`${resource}.destroy`), {
                        data: {ids}
                    });
                    toast.add({ severity: 'success', summary: 'Success', detail: 'Resource(s) deleted successfully.', life: 3000 });
                    // Refresh the page after delete
                    router.visit(window.location.href, { replace: true });
                } catch (error) {
                    console.error('Error deleting resource(s):', error);
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Resource(s) not deleted.', life: 3000 });
                }
            },
            reject: () => {
                toast.add({ severity: 'warn', summary: 'Cancelled', detail: 'You cancelled the deletion.', life: 3000 });
            }
        });
    };

    return { deleteResource };
}
