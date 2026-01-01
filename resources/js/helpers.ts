import axios from "axios";
import { type MenuEmits } from "primevue";
import { InertiaForm } from '@inertiajs/vue3';
import { computed, ref } from "vue";
import { useTemplateRef } from 'vue'

export const getClass = (cls: string) => {
    const documentStyle = getComputedStyle(document.documentElement);
    return documentStyle.getPropertyValue(cls);
};

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

export type ResourceData = { [x: string]: any, id: string | number };

export const populateForm = (data: ResourceData, form: InertiaForm<{ [x: string]: any }>) => {
    Object.keys(form).forEach((key) => {
        if (key in data) {
            form[key] = data[key as keyof typeof data];
        }
    });
};


/**
 * Formats a given date into a string representation in the format 'MM/DD/YYYY'.
 *
 * @param {Date} value - The date to be formatted.
 * @return {string} The formatted date string.
 */
export const formatDate = (value: string | Date) => {
    return new Date(value).toLocaleDateString('en-US', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

export function usePopup<T extends 'menu' | 'overlay' = 'menu'>(
    name: string
) {
    // Fully typed ref – no unknown, no any
    const ref = useTemplateRef<T extends 'menu' ? MenuEmits : any>(name as any)

    const toggle = (event?: Event) => {
        // @ts-ignore – PrimeVue components all have .toggle(event)
        ref.value?.toggle(event)
    }

    const show = (event?: Event) => ref.value?.show(event)
    const hide = () => ref.value?.hide()

    return { ref, toggle, show, hide }
}

export const useSelectedResources = () => {
    const selectedResources = ref<{ [x: string]: any; id: string | number }[]>([]);
    const selectedResourceIds = computed(() => selectedResources.value.map(resource => resource.id));

    return {
        selectedResources,
        selectedResourceIds,
    };
};
