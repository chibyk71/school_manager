import axios from 'axios';
import type {
    ColumnDefinition,
    Sort,
    BulkAction,
    FilterOperator
} from '@/types/datatables';

import { useToast } from 'primevue/usetoast';
import { computed, readonly, ref, shallowRef, onMounted } from 'vue';
import { LRUCache } from 'lru-cache';
import debounce from 'lodash/debounce';
import qs from 'qs'; // Make sure to: npm install qs
import throttle from 'lodash/throttle';

interface UseDataTableOptions<T> {
    initialParams?: Record<string, any>;
    initialData?: T[];
    bulkActions?: BulkAction[];
    maxRetries?: number;
    clientSideThreshold?: number;
    windowSize?: number;
    pageSize?: number;
    cacheMaxWindows?: number;
    /** Key to access the actual rows array from API response (e.g., 'data', 'rows', 'items') */
    dataProperty?: string;
}

export function useDataTable<T extends { id?: string | number } = any>(
    endpoint: string,
    columns: ColumnDefinition<T>[],
    options: UseDataTableOptions<T> = {}
) {
    const toast = useToast();

    const {
        initialParams = {},
        initialData = [] as T[],
        bulkActions = [],
        maxRetries = 3,
        clientSideThreshold = 1000,
        windowSize = 200,
        pageSize = 20,
        cacheMaxWindows = 5,
        dataProperty = undefined, // default: response.data.data → now configurable
    } = options;

    // =================================================================
    // STATE
    // =================================================================
    const dtRef = ref<any>(null);
    const rows = shallowRef<T[]>(initialData);           // Visible page (server-side)
    const allRows = shallowRef<T[]>([]);                 // Full dataset (client-side)
    const totalRecords = ref(0);
    const loading = ref(false);
    const fetching = ref(false);
    const error = ref<string | null>(null);

    const currentPage = ref(1);
    const perPage = ref(pageSize);

    const sorts = ref<Sort[]>([]);
    const filters = shallowRef<Record<string, { value: any; matchMode: string }>>({
        global: { value: '', matchMode: 'contains' },
    });

    const selectedRows = shallowRef<T[]>([]);
    const hiddenColumns = ref<string[]>([]);

    const isClientSide = ref(false);

    // Server-side window cache: windowKey → array of rows in that window
    const windowCache = new LRUCache<number, T[]>({
        max: cacheMaxWindows,
    });

    // =================================================================
    // FILTER INITIALIZATION
    // =================================================================

    const ensureFiltersInitialized = () => {
        for (const col of columns) {
            const key = String(col.field);
            if (key in filters.value || !col.filterable) continue;
            filters.value[key] = {
                value: null,
                matchMode: col.filterMatchMode ?? 'contains',
            };

            if (col.hidden) {
                hiddenColumns.value.push(col.field as string)
            }
        }
    };
    ensureFiltersInitialized();

    // =================================================================
    // HELPER: Extract data array from response using dataProperty
    // =================================================================

    const extractData = (responseData: any): T[] => {
        return dataProperty ? responseData[dataProperty].data ?? [] : responseData.data ?? [];
    };

    const extractTotal = (responseData: any): number => {
        return dataProperty ? responseData[dataProperty].total : responseData.total ?? 0;
    };


    // =================================================================
    // CORE FETCH – Fully Revised with qs + Laravel Purity Support
    // =================================================================

    /**
     * Core data fetching function with hybrid mode support:
     * - full_load: Fetch entire dataset (client-side when ≤ threshold)
     * - window: Fetch larger chunk for server-side prefetching
     * - regular: Single paginated page
     *
     * Now uses 'qs' library to properly format filters & sorts for Laravel Purity
     */
    const fetchData = async (page: number, fullLoad = false, isWindow = false) => {
        if (fetching.value) return; // Prevent concurrent requests

        fetching.value = true;
        loading.value = true;
        error.value = null;

        try {
            // =================================================================
            // 1. Calculate pagination / window parameters
            // =================================================================
            const pagesPerWindow = windowSize / perPage.value; // e.g., 200 rows / 20 per page = 10 pages per window
            const windowPage = isWindow
                ? Math.floor((page - 1) / pagesPerWindow) + 1 // Which window contains the requested page
                : page;

            // =================================================================
            // 2. Map PrimeVue filters → Laravel Purity format
            // =================================================================
            const purityFilters: Record<string, any> = {};

            /**
             * Maps PrimeVue's FilterMatchMode (or string equivalents) to Laravel Purity operators
             *
             * PrimeVue match modes: https://primevue.org/datatable/#filtermatchmode
             * Laravel Purity operators: https://abbasudo.github.io/laravel-purity/#operators
             */
            const matchModeToOperator: Record<string, FilterOperator> = {
                // Text-based filters
                contains: '$contains',
                notContains: '$notContains',
                startsWith: '$startsWith',
                endsWith: '$endsWith',

                // Equality
                equals: '$eq',
                notEquals: '$ne',

                // Case-insensitive variants (Purity supports them)
                containsCaseInsensitive: '$containsc',
                notContainsCaseInsensitive: '$notContainsc',
                startsWithCaseInsensitive: '$startsWithc',
                endsWithCaseInsensitive: '$endsWithc',
                equalsCaseInsensitive: '$eqc',

                // Numeric & date comparisons
                lt: '$lt',           // less than
                lte: '$lte',         // less than or equal
                gt: '$gt',           // greater than
                gte: '$gte',         // greater than or equal

                // Array-based
                in: '$in',
                notIn: '$notIn',

                // Range
                between: '$between',
                notBetween: '$notBetween',

                // Null checks
                is: '$null',         // PrimeVue uses "is" for null
                isNot: '$notNull',   // PrimeVue uses "isNot" for not null

                // Date-specific (PrimeVue provides these)
                dateIs: '$eq',           // exact date match
                dateIsNot: '$ne',
                dateBefore: '$lt',
                dateAfter: '$gt',
            } as const;

            // Process all column filters (skip 'global' – handled separately as 'search')
            Object.keys(filters.value).forEach((field) => {
                const filter = filters.value[field];
                if (field === 'global' || filter.value === null || filter.value === '') {
                    return; // Skip empty or global (already sent as 'search')
                }

                const operator = matchModeToOperator[filter.matchMode] || '$eq';
                purityFilters[field] = { [operator]: filter.value };
            });

            // =================================================================
            // 3. Map PrimeVue multi-sort → Laravel Purity format
            // =================================================================
            const puritySorts: string[] = sorts.value.map((sort) => {
                const order = sort.order === 'asc' ? 'asc' : 'desc';
                return `${sort.field}:${order}`;
            });

            // =================================================================
            // 4. Build base request parameters
            // =================================================================
            const baseParams: Record<string, any> = {
                // Pagination / window control
                page: fullLoad ? 1 : windowPage,
                per_page: fullLoad
                    ? clientSideThreshold + 100 // Fetch slightly more than threshold to be safe
                    : isWindow
                        ? windowSize
                        : perPage.value,

                // Global search (handled separately in backend HasTableQuery)
                search: filters.value.global?.value?.trim() || undefined,

                // Laravel Purity sorting (multi-sort supported)
                ...(puritySorts.length > 0 && { sort: puritySorts }),

                // Laravel Purity filtering (nested bracket format via qs)
                ...(Object.keys(purityFilters).length > 0 && { filters: purityFilters }),

                // Hybrid mode flags
                full_load: fullLoad || undefined,

                // Any static params (e.g., tenant_id, status=active)
                ...initialParams,
            };

            // =================================================================
            // 5. Serialize params using 'qs' for correct bracket/array encoding
            // =================================================================
            const queryString = qs.stringify(baseParams, {
                arrayFormat: 'indices',   // Ensures sort[0], sort[1], filters[field][$eq], etc.
                encode: true,             // Proper URL encoding
                skipNulls: true,          // Don't send undefined/null values
            });

            const url = queryString ? `${endpoint}?${queryString}` : endpoint;

            // =================================================================
            // 6. Perform request with retry logic
            // =================================================================
            let attempts = 0;
            let response;

            while (attempts < maxRetries) {
                try {
                    response = await axios.get(url);
                    break; // Success → exit retry loop
                } catch (err: any) {
                    attempts++;

                    if (attempts >= maxRetries) {
                        // Final failure
                        const msg = err.response?.data?.message || err.message || 'Network error';
                        error.value = msg;
                        toast.add({
                            severity: 'error',
                            summary: 'Failed to load data',
                            detail: msg,
                            life: 8000,
                        });
                        throw err; // Re-throw to exit
                    }

                    // Exponential backoff before retry
                    await new Promise((resolve) => setTimeout(resolve, 1000 * Math.pow(2, attempts)));
                }
            }

            // =================================================================
            // 7. Extract and process response data
            // =================================================================
            const data = response!.data;

            // Support nested data (e.g., dataProperty = 'roles.data')
            const rowsData = extractData(data);
            const total = extractTotal(data);

            // =================================================================
            // 8. Update state based on fetch mode
            // =================================================================
            if (fullLoad) {
                // Client-side mode: store everything
                allRows.value = rowsData;
                totalRecords.value = total || rowsData.length;
                isClientSide.value = true;
            } else if (isWindow) {
                // Server-side: cache window for fast scrolling
                windowCache.set(windowPage, rowsData);
                totalRecords.value = total || totalRecords.value;
            } else {
                // Single page (fallback or initial load)
                rows.value = rowsData;
                totalRecords.value = total || 0;
            }
        } catch (finalError) {
            // Ensure loading state is cleared even on error
            console.error('[useDataTable] Fetch failed after retries:', finalError);
        } finally {
            // Always reset loading flags
            loading.value = false;
            fetching.value = false;
        }
    };

    // =================================================================
    // CACHE HELPERS
    // =================================================================

    const getPageFromCache = (page: number): T[] => {
        const pagesPerWindow = windowSize / perPage.value;
        const windowKey = Math.floor((page - 1) / pagesPerWindow) + 1;
        const windowData = windowCache.get(windowKey);
        if (!windowData) return [];

        const offset = ((page - 1) % pagesPerWindow) * perPage.value;
        return windowData.slice(offset, offset + perPage.value);
    };

    // =================================================================
    // PREFETCH
    // =================================================================

    const prefetchNextWindow = throttle(async (currPage: number) => {
        if (isClientSide.value) return;

        const pagesPerWindow = windowSize / perPage.value;
        const positionInWindow = (currPage - 1) % pagesPerWindow;

        if (positionInWindow / pagesPerWindow >= 0.75) {
            const nextWindowKey = Math.floor((currPage - 1) / pagesPerWindow) + 2;
            if (!windowCache.has(nextWindowKey)) {
                const startPage = (nextWindowKey - 1) * pagesPerWindow + 1;
                await fetchData(startPage, false, true);
            }
        }
    }, 500);

    // =================================================================
    // LAZY LOAD (server-side only)
    // =================================================================

    const loadLazyData = async () => {
        if (isClientSide.value || fetching.value) return;

        // Try cache first
        const cached = getPageFromCache(currentPage.value);
        if (cached.length > 0) {
            rows.value = cached;
            prefetchNextWindow(currentPage.value);
            return;
        }

        // Cache miss → fetch centered window
        const pagesPerWindow = windowSize / perPage.value;
        const centeredStart = Math.max(1, currentPage.value - Math.floor(pagesPerWindow / 2));
        await fetchData(centeredStart, false, true);
        rows.value = getPageFromCache(currentPage.value);
        prefetchNextWindow(currentPage.value);
    };

    // =================================================================
    // EVENT HANDLERS
    // =================================================================

    const onPage = (event: { page: number; rows: number }) => {
        currentPage.value = event.page + 1;
        perPage.value = event.rows;

        if (isClientSide.value) {
            // PrimeVue handles pagination automatically
            return;
        }

        loadLazyData();
    };

    const onSort = (event: { multiSortMeta?: Sort[] }) => {
        if (event.multiSortMeta) {
            sorts.value = event.multiSortMeta;
        }

        if (isClientSide.value) {
            // PrimeVue handles sorting
            return;
        }

        windowCache.clear();
        currentPage.value = 1;
        loadLazyData();
    };

    const onFilter = debounce(() => {
        if (isClientSide.value) {
            // PrimeVue handles filtering
            return;
        }

        windowCache.clear();
        currentPage.value = 1;
        loadLazyData();
    }, 400);

    // =================================================================
    // INITIAL LOAD
    // =================================================================
    onMounted(async () => {

        // Step 1: Load first page to get total count
        if (!totalRecords.value && !rows.value) {
            await fetchData(1);
        }

        if (totalRecords.value <= clientSideThreshold) {
            // Small dataset → full client-side
            await fetchData(1, true);
            rows.value = allRows.value; // Bind full data
        } else {
            // Large dataset → server-side with first window
            await fetchData(1, false, true);
            rows.value = getPageFromCache(1);
        }
    });

    // =================================================================
    // REFRESH & BULK
    // =================================================================

    const refresh = () => {
        selectedRows.value = [];
        currentPage.value = 1;
        if (!isClientSide.value) windowCache.clear();
        if (isClientSide.value) {
            fetchData(1, true);
        } else {
            fetchData(1, false, true);
        }
    };

    const performBulkAction = async (action: string) => {
        if (!selectedRows.value.length) return;

        try {
            await axios.post(`${endpoint}/bulk`, {
                action,
                ids: selectedRows.value.map(r => r.id),
            });
            toast.add({ severity: 'success', summary: 'Bulk action completed' });
            refresh();
        } catch (err: any) {
            toast.add({ severity: 'error', summary: 'Bulk action failed', detail: err.message });
        }
    };

    // =================================================================
    // EXPORT
    // =================================================================

    const exportData = async (exportAll = false, visibleOnly = false) => {
        // Same logic as before, but using extractData if needed
        // Simplified here — adapt if your export endpoint returns nested data
        if (isClientSide.value) {
            // const dataToExport = visibleOnly ? rows.value : exportAll ? allRows.value : rows.value;
            // if (!dataToExport.length) {
            //     toast.add({ severity: 'warn', summary: 'Nothing to export' });
            //     return;
            // }

            // const headers = Object.keys(dataToExport[0] as any).join(',');
            // const lines = dataToExport.map(row => Object.values(row as any).join(','));
            // const csv = [headers, ...lines].join('\n');
            // const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            // downloadBlob(blob, 'export.csv');
            dtRef.value.exportCSV();
        } else {
            const params = {
                export_all: exportAll,
                visible_only: visibleOnly,
                sorts: JSON.stringify(sorts.value),
                filters: JSON.stringify(filters.value),
                search: filters.value.global.value,
                ...initialParams,
            };

            try {
                const response = await axios.get(`${endpoint}/export`, {
                    params,
                    responseType: 'blob',
                });
                downloadBlob(response.data, 'export.csv');
            } catch {
                toast.add({ severity: 'error', summary: 'Export failed' });
            }
        }
    };

    const downloadBlob = (blob: Blob, filename: string) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    };

    // =================================================================
    // RETURNED API
    // =================================================================

    const tableData = computed(() => isClientSide.value ? allRows.value : rows.value);
    const isLazy = computed(() => !isClientSide.value);

    return {
        // Bind these to DataTable
        dtRef,
        tableData,                    // :value="tableData"
        isLazy,                       // :lazy="isLazy"
        totalRecords: readonly(totalRecords),
        loading: readonly(loading),
        error: readonly(error),

        perPage,
        currentPage: readonly(currentPage),

        onPage,
        onSort,
        onFilter,

        sorts: readonly(sorts),
        filters,

        selectedRows,
        hiddenColumns,

        visibleColumns: computed(() => columns.filter(c => !hiddenColumns.value.includes(String(c.field)))),

        refresh,
        performBulkAction,
        exportData,
        bulkActions: computed(() => bulkActions),
        isClientSide: readonly(isClientSide),
    };
}
