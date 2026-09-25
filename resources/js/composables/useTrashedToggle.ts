// composables/useTrashedToggle.ts
import { inject, ref, type Ref } from 'vue';

/**
 * Optional DataTable inject surface.
 * Trash is resource-specific (Phase 5): pages that need it own showTrashed
 * and pass the param via initialParams. AdvancedDataTable no longer owns trash.
 * When no provider is present (typical — PageHeader is above the table),
 * callers get a local no-op ref so action visibility stays safe.
 */
interface DataTableApi {
    showTrashed?: Ref<boolean>;
    toggleTrashed?: () => void;
    refresh?: () => void;
}

export function useTrashedToggle() {
    const api = inject<DataTableApi | null>('dataTableApi', null);

    if (!api?.showTrashed || !api?.toggleTrashed) {
        return {
            showTrashed: ref(false),
            toggleTrashed: () => {},
        };
    }

    return {
        showTrashed: api.showTrashed,
        toggleTrashed: api.toggleTrashed,
    };
}
