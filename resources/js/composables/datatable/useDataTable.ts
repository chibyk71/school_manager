// resources/js/composables/useDataTable.ts
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce'
import type { ColumnDefinition, DataTableResponse } from '@/types'

export function useDataTable<T = any>(
    endpoint: string,
    columns: ColumnDefinition<T>[],
    options: {
        initialParams?: Record<string, any>
        globalFilterFields?: string[]
        bulkActions?: any[]
    } = {}
) {
    // State
    const rows = ref<T[]>([])
    const totalRecords = ref(0)
    const loading = ref(false)
    const currentPage = ref(1)
    const perPage = ref(10)
    const sortField = ref<string>('')
    const sortOrder = ref<1 | -1>(1)
    const selectedRows = ref<T[]>([])
    const hiddenColumns = ref<string[]>([])

    // Filters (PrimeVue format)
    const filters = ref<Record<string, any>>({
        global: { value: null, matchMode: 'contains' }
    })

    // Initialize column filters
    columns.forEach(col => {
        const key = String(col.field)
        if (!filters.value[key]) {
            filters.value[key] = { value: null, matchMode: col.matchMode || 'contains' }
        }
    })

    const fetchData = async () => {
        loading.value = true
        try {
            const sortOrderStr = sortOrder.value === 1 ? 'asc' : 'desc'
            const response = await axios.get<DataTableResponse<T>>(endpoint, {
                params: {
                    page: currentPage.value,
                    per_page: perPage.value,
                    sort_field: sortField.value || undefined,
                    sort_order: sortField.value ? sortOrderStr : undefined,
                    search: filters.value.global.value || undefined,
                    filters: JSON.stringify(filters.value),
                    ...options.initialParams
                }
            })

            rows.value = response.data.data
            totalRecords.value = response.data.totalRecords
        } catch (error) {
            console.error('Failed to fetch table data:', error)
        } finally {
            loading.value = false
        }
    }

    const debouncedFetch = debounce(fetchData, 400)

    watch(filters, debouncedFetch, { deep: true })
    watch([currentPage, perPage], fetchData)

    const onPage = (event: any) => {
        currentPage.value = event.page + 1
        perPage.value = event.rows
    }

    const onSort = (event: any) => {
        sortField.value = event.sortField
        sortOrder.value = event.sortOrder
    }

    const refresh = () => {
        currentPage.value = 1
        fetchData()
    }

    onMounted(fetchData)

    return {
        rows,
        totalRecords,
        loading,
        perPage,
        currentPage,
        sortField,
        sortOrder,
        selectedRows,
        hiddenColumns,
        filters,
        onPage,
        onSort,
        refresh,
        visibleColumns: computed(() =>
            columns.filter(col => !hiddenColumns.value.includes(col.field as string))
        )
    }
}