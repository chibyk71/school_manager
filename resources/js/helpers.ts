import axios from "axios";
import { useConfirm, useToast } from "primevue";

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
     * @param {number|string} id - The ID of the resource to delete.
     * @param {string} [url] - The URL to make the request to. Defaults to `/api/${resource}/${id}`.
     * @returns {Promise<void>}
     */
    const deleteResource = (resource: string, id: number | string, url?: string) => {
        confirm.require({
            message: 'Do you want to delete this record?',
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
            accept: () => {
                axios.delete(url || `${resource}/${id}`)
                    .then(() => {
                        toast.add({ severity: 'success', summary: 'Success', detail: 'Resource deleted successfully.', life: 3000 });
                    })
                    .catch(() => {
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Resource not deleted.', life: 3000 });
                    });
            },
            reject: () => {
                toast.add({ severity: 'warn', summary: 'Cancelled', detail: 'You cancelled the deletion.', life: 3000 });
            }
        });
    };

    return { deleteResource };
}
