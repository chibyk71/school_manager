import axios from "axios";
import { useConfirm, useDialog, useToast } from "primevue";
import { InertiaForm, router } from '@inertiajs/vue3';
import { reactive, ref } from "vue";
import { ModalComponentDirectory } from "./Components/Modals/ModalDirectory";

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
                    const normalizedIds = Array.isArray(ids) ? ids : [ids];
                    const requestData = { data: { ids: normalizedIds } };

                    axios.delete(url || route(`${resource}.destroy`), requestData)
                        .then(({ data, status }) => {
                            if (status !== 200) {
                                toast.add({ severity: 'error', summary: 'Error', detail: data.message || 'Resource(s) not deleted.', life: 3000 });
                            } else {
                                toast.add({ severity: 'success', summary: 'Success', detail: data.message || 'Resource(s) deleted successfully.', life: 3000 });
                                // Refresh the page after delete
                                router.visit(window.location.href, { replace: true, preserveScroll: true });
                            }
                        });
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

export const fetchSelectOptionsFromDB = (resource: string, page?: number) => {
    try {
        const res = axios.get(`options`, { params: { resource } })
            .then(({ data }) => {
                return data.data
            });
        return res
    } catch (error) {
        console.error('Error fetching options:', error);
        throw error;
    }
};

export const useSubmitForm = () => {
    const toast = useToast();

    /**
     * Submits an Inertia form to the server and handles the response.
     * @param {InertiaForm<{}>} form - The form to be submitted.
     * @param {string} resource - The resource name.
     * @param {string|number} [id] - The ID of the resource to be updated or created.
     * @returns {Promise<void>}
     */
    const submitForm = async (form: InertiaForm<{}>, resource: string, id?: string | number, callbacks?: { onSuccess?: (props: any) => void, onError?: (errors: any) => void }, customRoute?: string) => {
        const routeName = customRoute || route(id ? `${resource}.update` : `${resource}.store`, id);

        form.post(customRoute || routeName, {
            onSuccess: ({ props }) => {
                callbacks?.onSuccess?.(props);
                if (props.flash.success) {
                    toast.add({ severity: 'success', summary: 'Success', detail: props.flash.success, life: 3000 });
                }
                modals.close();
            },
            onError: (errors) => {
                console.error('Error submitting form:', errors);
                callbacks?.onError?.(errors);
                if (errors) {
                    toast.add({ severity: 'error', summary: 'Error', detail: errors, life: 3000 });
                }
            }
        });
    };

    return { submitForm };
};

/**
 * Returns a reactive object containing methods to manage modals.
 *
 * @returns {{
 *  items: string[],
 *  open: (id: string) => void,
 *  close: (id?: string) => void,
 *  closeAll: () => void,
 *  prepend: (id: string) => void
 * }}
 */
export const modals = reactive({
    items: [] as { id: string, data?: { [x: string]: any, resource_data?: ResourceData } }[],
    open: (id: string, data?: { [x: string]: any; resource_data?: ResourceData }) => {
        modals.items.push({ id, data });
    },
    close(id?: string) {
        if (id) {
            const index = this.items.findIndex(item => item.id === id);
            if (index > -1) {
                this.items.splice(index, 1);
            }
        } else {
            this.items.shift();
        }
    },
    closeAll() {
        this.items = [];
    },
    prepend(id: string, data?: any) {
        this.items.unshift({ id, data });
    },
    getData(id: string) {
        const modal = this.items.find(item => item.id === id);
        return modal?.data;
    }
});

export type ResourceData = { [x: string]: any, id: string | number };

export const populateForm = (data: ResourceData, form: InertiaForm<{ [x: string]: any }>) => {
    Object.keys(form).forEach((key) => {
        if (key in data) {
            form[key] = data[key as keyof typeof data];
        }
    });
};
