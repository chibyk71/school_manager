// resources/js/composables/useDataTable.ts
import { shallowRef, ref, watch, computed, onMounted, type ShallowRef, readonly } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'
import type {
    ColumnDefinition,
    DataTableResponse,
    Sort,
    BulkAction
} from '@/types/datatables'
import { debounce } from 'lodash'

interface UseDataTableOptions<T> {
    initialParams?: Record<string, any>
    initialData?: T[]
    bulkActions?: BulkAction[]
    useInertia?: boolean
    /** Max retry attempts on network failure */
    maxRetries?: number
}

/**
 * Enterprise-grade DataTable composable
 * • Fully supports Inertia + Axios
 * • Multi-column sorting
 * • Shallow refs for 10k+ rows performance
 * • Safe filter initialization (no PrimeVue crashes)
 * • Exponential backoff retry
 * • SSR-friendly
 */
export function useDataTable<T extends { id?: string | number } = any>(
    endpoint: string,
    columns: ColumnDefinition<T>[],
    options: UseDataTableOptions<T> = {}
) {
    const toast = useToast()

    const {
        initialParams = {},
        initialData,
        bulkActions = [],
        useInertia = true,
        maxRetries = 3
    } = options

    // =================================================================
    // 1. STATE – optimized with shallowRef for large datasets
    // =================================================================
    const rows = shallowRef<T[]>(initialData ?? [])
    const totalRecords = ref(0)
    const loading = ref(false)
    const fetching = ref(false) // prevents double-fetch on rapid triggers
    const error = ref<string | null>(null)

    // Pagination
    const currentPage = ref(1)
    const perPage = ref(10)

    // Sorting – now supports multi-column
    const sorts = ref<Sort[]>([])

    // Selection & visibility
    const selectedRows = shallowRef<T[]>([])
    const hiddenColumns = ref<string[]>([])

    // Filters – shallowRef + guaranteed initialization
    const filters = shallowRef<Record<string, { value: any; matchMode: string }>>({
        global: { value: '', matchMode: 'contains' }
    })

    // Ensure every column has a filter model (PrimeVue requires this)
    // Ensure every filterable column has a model (PrimeVue requires this)
    const ensureFiltersInitialized = () => {
        for (const col of columns) {
            const key = String(col.field)
            if (Object.prototype.hasOwnProperty.call(filters.value, key)) continue
            if (!col.filterable) continue
            filters.value[key] = {
                value: null,
                matchMode: col.filterMatchMode ?? 'contains'
            }
        }
    }
    ensureFiltersInitialized()

    // =================================================================
    // 2. FETCH LOGIC – with retry + exponential backoff
    // =================================================================
    const fetchWithRetry = async (attempt = 1): Promise<DataTableResponse<T>> => {
        const params = {
            page: currentPage.value,
            per_page: perPage.value,
            sorts: sorts.value.length ? JSON.stringify(sorts.value) : undefined,
            search: filters.value.global.value || undefined,
            filters: JSON.stringify(filters.value),
            ...initialParams
        }

        if (useInertia) {
            router.get(endpoint, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['data', 'totalRecords'], // optional: only reload table data
            })
            return { data: [], totalRecords: 0, page: 1, pageSize: perPage.value }
        }

        const response = await axios.get<DataTableResponse<T>>(endpoint, { params })
        return response.data
    }

    const fetchData = async () => {
        if (fetching.value) return
        fetching.value = true
        loading.value = true
        error.value = null

        let attempt = 1
        while (attempt <= maxRetries) {
            try {
                const data = await fetchWithRetry(attempt)

                rows.value = data.data ?? []
                totalRecords.value = data.totalRecords ?? 0
                break // success → exit retry loop
            } catch (err: any) {
                if (attempt === maxRetries) {
                    const msg = err.response?.data?.message || err.message || 'Network error'
                    error.value = msg
                    toast.add({
                        severity: 'error',
                        summary: 'Failed to load data',
                        detail: msg,
                        life: 8000
                    })
                } else {
                    // Exponential backoff: 300 → 900 → 2700ms
                    await new Promise(r => setTimeout(r, 300 * 2 ** (attempt - 1)))
                    attempt++
                }
            } finally {
                loading.value = false
                fetching.value = false
            }
        }
    }

    // =================================================================
    // 3. WATCHERS – shallow + debounced
    // =================================================================
    const debouncedFetch = debounce(() => {
        currentPage.value = 1 // reset page on filter/sort change
        fetchData()
    }, 400)

    watch([filters, sorts], debouncedFetch, { deep: false }) // shallow = fast
    watch([currentPage, perPage], fetchData)

    // =================================================================
    // 4. EVENT HANDLERS
    // =================================================================
    const onPage = (event: { page: number; rows: number }) => {
        currentPage.value = event.page + 1
        perPage.value = event.rows
    }

    const onSort = (event: { sortField: string; sortOrder: 1 | -1; multiSortMeta: Sort[] }) => {
        if (event.multiSortMeta && event.multiSortMeta.length) {
            sorts.value = event.multiSortMeta
        } else {
            // Single sort fallback
            sorts.value = [{ field: event.sortField, order: event.sortOrder === 1 ? 'asc' : 'desc' }]
        }
        fetchData()
    }

    const refresh = () => {
        selectedRows.value = []
        currentPage.value = 1
        fetchData()
    }

    // =================================================================
    // 5. BULK ACTIONS & EXPORT
    // =================================================================
    const performBulkAction = async (action: string, payload = {}) => {
        loading.value = true
        try {
            await axios.post(`${endpoint}/bulk`, {
                action,
                ids: selectedRows.value.map(r => r.id),
                ...payload
            })
            toast.add({ severity: 'success', summary: 'Success', detail: 'Action completed' })
            selectedRows.value = []
            refresh()
        } catch (err: any) {
            toast.add({
                severity: 'error',
                summary: 'Failed',
                detail: err.response?.data?.message || 'Action failed',
                life: 8000
            })
        } finally {
            loading.value = false
        }
    }

    const exportData = async (format: 'csv' | 'excel' = 'csv') => {
        try {
            const resp = await axios.get(`${endpoint}/export`, {
                params: { format, filters: JSON.stringify(filters.value) },
                responseType: 'blob'
            })
            const url = URL.createObjectURL(resp.data)
            const a = document.createElement('a');
            a.href = url;
            a.download = `export.${format === 'csv' ? 'csv' : 'xlsx'}`
            a.click()
            URL.revokeObjectURL(url)
        } catch {
            toast.add({ severity: 'error', summary: 'Export failed' })
        }
    }

    // =================================================================
    // 6. LIFECYCLE – SSR safe
    // =================================================================
    onMounted(() => {
        if (!initialData?.length) fetchData()
    })

    // =================================================================
    // 7. RETURN – clean & typed API
    // =================================================================
    return {
        // Data
        rows: computed(() => rows.value),
        totalRecords: readonly(totalRecords),
        loading: loading,
        error: readonly(error),

        // Pagination
        currentPage: readonly(currentPage),
        perPage,
        onPage,

        // Sorting
        sorts: readonly(sorts),
        onSort,

        // Selection & visibility
        selectedRows,
        hiddenColumns,

        // Filters – safe to bind directly to PrimeVue
        filters: filters,

        // Computed visible columns (memoized)
        visibleColumns: computed(() => columns.filter(c => !hiddenColumns.value.includes(String(c.field)))),

        // Actions
        refresh,
        performBulkAction,
        exportData,
        bulkActions: computed(() => bulkActions),
    }
}
