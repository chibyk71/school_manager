// composables/useTrashedToggle.ts
import { inject, ref, type Ref } from 'vue';

interface DataTableApi {
    showTrashed: Ref<boolean>;
    toggleTrashed: () => void;
    refresh: () => void;
}

export function useTrashedToggle() {
    const api = inject<DataTableApi>('dataTableApi');

    if (!api) {
        console.warn('[useTrashedToggle] No DataTable found in parent tree');
        return {
            showTrashed: ref(false),
            toggleTrashed: () => {}
        };
    }

    return {
        showTrashed: api.showTrashed,
        toggleTrashed: api.toggleTrashed
    };
}
