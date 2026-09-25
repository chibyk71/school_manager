<script setup lang="ts" generic="T extends Record<string, any>">
/**
 * AdvancedDataTable — presentation + orchestration over useDataTableQuery.
 * HTTP/cache/retry owned by TanStack Query via DataTableDataSource.
 */
import { computed, provide, ref, watch } from 'vue'
import { useDataTableQuery } from '@/datatable/useDataTableQuery'
import {
    primeVueFiltersToCanonical,
    primeVueSortToCanonical,
    extractGlobalSearch,
} from '@/datatable/adapters/primevue'
import { DEFAULT_PER_PAGE } from '@/datatable/types'
import type { DataTableResponse } from '@/datatable/types'

import DataTableHeader from './DataTableHeader.vue'
import DataTableEmptyState from './DataTableEmptyState.vue'
import DataTableLoadingState from './DataTableLoadingState.vue'
import RenderCell from './RenderCell.vue'
import ActionsDropdown from './ActionsDropdown.vue'

import {
    Button,
    Column,
    DataTable,
    DatePicker,
    InputNumber,
    InputText,
    Menu,
    RadioButton,
    Select,
} from 'primevue'

import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { formatDate } from '@/helpers'

const props = defineProps<{
    endpoint: string
    /**
     * Optional Inertia first-page rows. Seeded into TanStack Query as initialData
     * for the default query identity only — not a parallel data mode.
     */
    initialData?: T[]
    /** Optional column metadata accompanying initialData (from backend). */
    initialColumns?: ColumnDefinition<T>[]
    initialParams?: Record<string, any>
    columns: ColumnDefinition<T>[]
    bulkActions?: BulkAction[]
    virtualScroller?: boolean
    actions?: TableAction<T>[]
}>()

const extraParams = computed(() => props.initialParams ?? {})

/** Build a canonical seed response for TanStack initialData (page 1 only). */
const initialResponse = computed((): DataTableResponse<T> | null => {
    if (!props.initialData?.length) return null
    const cols = (props.initialColumns ?? props.columns ?? []) as any[]
    return {
        data: props.initialData,
        columns: cols as any,
        meta: {
            currentPage: 1,
            perPage: DEFAULT_PER_PAGE,
            // Unknown until first network response; seed uses row count as lower bound only for empty UX
            total: props.initialData.length,
            lastPage: 1,
        },
    }
})

const {
    query,
    rows,
    columns: serverColumns,
    meta,
    isPending,
    isFetching,
    isError,
    error,
    isPlaceholderData,
    setPage,
    setPerPage,
    setSearchAndFilters,
    setSorts,
    refresh,
} = useDataTableQuery<T>({
    resource: props.endpoint,
    initialQuery: { page: 1, perPage: DEFAULT_PER_PAGE },
    extraParams,
    initialResponse: initialResponse.value,
})

const dtRef = ref<any>(null)
const selectedRows = ref<T[]>([])
const hiddenColumns = ref<string[]>([])
const exportMenu = ref()
const applyingFilters = ref(false)

// PrimeVue filter model (presentation only)
const filters = ref<Record<string, { value: any; matchMode: string }>>({
    global: { value: '', matchMode: 'contains' },
})

const displayColumns = computed(() => {
    const base = Array.isArray(props.columns) ? props.columns : []
    const server = serverColumns.value
    if (!server?.length) return base
    return base.map((col) => {
        const s = server.find((c: any) => c.field === col.field)
        if (!s) return col
        return {
            ...col,
            // Backend capabilities are authoritative when present
            sortable: s.sortable !== undefined ? s.sortable : col.sortable,
            filterable: s.filterable !== undefined ? s.filterable : col.filterable,
            searchable: s.searchable !== undefined ? s.searchable : (col as any).searchable,
            exportable: s.exportable !== undefined ? s.exportable : col.exportable,
            filterType: col.filterType ?? s.filterType,
            filterOptions: col.filterOptions ?? s.filterOptions,
            filterMatchMode: col.filterMatchMode ?? s.filterMatchMode,
            hidden: col.hidden ?? s.hidden,
        }
    })
})

const visibleColumns = computed(() =>
    displayColumns.value.filter((c) => !hiddenColumns.value.includes(String(c.field))),
)

watch(
    displayColumns,
    (cols) => {
        if (hiddenColumns.value.length) return
        const initial = cols
            .filter((c) => c.hidden || (c as any).defaultHidden)
            .map((c) => String(c.field))
        if (initial.length) hiddenColumns.value = initial
    },
    { immediate: true },
)

const tableData = computed(() => rows.value)
const loading = computed(() => isPending.value || (isFetching.value && !isPlaceholderData.value))
/** Authoritative total from live query meta — never a stale Inertia prop. */
const totalRecords = computed(() => meta.value.total)
const perPage = computed({
    get: () => query.value.perPage,
    set: (v: number) => setPerPage(v),
})

const safeBulkActions = computed(() => props.bulkActions ?? [])

/**
 * Searchable fields from capability metadata only.
 * searchable is independent of filterable.
 */
const searchableFields = computed(() =>
    displayColumns.value
        .filter((c) => (c as any).searchable === true)
        .map((c) => String(c.field)),
)

function onPage(event: { page: number; rows: number }) {
    const nextPage = (event.page ?? 0) + 1
    if (event.rows && event.rows !== query.value.perPage) {
        setPerPage(event.rows)
    } else {
        setPage(nextPage)
    }
}

function onSortHandler(event: {
    sortField?: string
    sortOrder?: number | null
    multiSortMeta?: Array<{ field: string; order: number | null }>
}) {
    const sorts = primeVueSortToCanonical(event.sortField, event.sortOrder, event.multiSortMeta)
    setSorts(sorts.length ? sorts : undefined)
}

/**
 * Single path: PrimeVue filters → one canonical search+filters update.
 * Guard against re-entrant / duplicate event paths.
 */
function applyFiltersFromPrimeVue() {
    if (applyingFilters.value) return
    applyingFilters.value = true
    try {
        const search = extractGlobalSearch(filters.value)
        const canonical = primeVueFiltersToCanonical(filters.value)
        setSearchAndFilters(search, canonical)
    } finally {
        // nextTick would be better; microtask is enough to collapse same-tick doubles
        queueMicrotask(() => {
            applyingFilters.value = false
        })
    }
}

function onFilter() {
    applyFiltersFromPrimeVue()
}

const showTrashed = ref(false)
function toggleTrashed() {
    showTrashed.value = !showTrashed.value
    refresh()
}

provide('dataTableApi', {
    showTrashed,
    toggleTrashed,
    refresh,
})

defineExpose({
    refresh,
    exportData: () => {
        console.warn('[AdvancedDataTable] export is owned by Phase 6')
    },
})

function toggleExportMenu(e: Event) {
    exportMenu.value?.toggle(e)
}
function handleExportVisible() {
    console.warn('[AdvancedDataTable] export is owned by Phase 6')
}
function handleExportAll() {
    console.warn('[AdvancedDataTable] export is owned by Phase 6')
}

const actions = computed(() => props.actions)
</script>

<template>
    <div class="datatable-wrapper">
        <DataTableHeader
            :selected-rows="selectedRows"
            :bulk-actions="safeBulkActions"
            :columns="displayColumns as any"
            v-model:hidden-columns="hiddenColumns"
            v-model:global-search="filters.global.value"
            @refresh="refresh"
            @update:global-search="applyFiltersFromPrimeVue"
        />

        <div v-if="isError" class="p-4 text-sm text-red-600 dark:text-red-400" role="alert">
            {{ error?.message ?? 'Failed to load table data.' }}
            <Button label="Retry" size="small" class="ml-2" @click="() => refresh()" />
        </div>

        <DataTable
            :ref="(el: any) => (dtRef = el)"
            :value="tableData"
            :loading="loading"
            lazy
            paginator
            :rows="perPage"
            :total-records="totalRecords"
            :first="(meta.currentPage - 1) * meta.perPage"
            :virtual-scroller-options="virtualScroller ? { itemSize: 56 } : undefined"
            @page="onPage"
            @sort="onSortHandler"
            @filter="onFilter"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            paginator-template="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
            current-page-report-template="{first} - {last} of {totalRecords}"
            v-model:selection="selectedRows"
            data-key="id"
            class="p-datatable-sm !rounded-none"
            striped-rows
            removable-sort
            scrollable
            :scroll-height="virtualScroller ? '600px' : undefined"
            :global-filter-fields="searchableFields"
            filter-display="menu"
            :filters="filters"
        >
            <Column selection-mode="multiple" header-style="width: 3.5rem" body-style="text-align: center" />

            <Column
                v-for="col in visibleColumns"
                :key="col.field"
                :field="col.field as string"
                :header="col.header"
                :sortable="col.sortable !== false"
                :header-class="col.headerClass"
                :body-class="col.bodyClass"
                :style="col.width ? { width: col.width } : undefined"
                :frozen="col.frozen"
            >
                <template #body="slotProps">
                    <RenderCell v-if="col.render" :render-result="col.render(slotProps.data)" :row="slotProps.data" />
                    <template v-else-if="col.formatter">
                        {{ col.formatter(slotProps.data[col.field], slotProps.data) }}
                    </template>
                    <template v-else-if="col.filterType === 'boolean'">
                        <i
                            :class="
                                slotProps.data[col.field]
                                    ? 'pi pi-check-circle text-green-600'
                                    : 'pi pi-times-circle text-red-600'
                            "
                            aria-hidden="true"
                        />
                    </template>
                    <template v-else-if="col.filterType === 'date' && slotProps.data[col.field]">
                        {{ formatDate(slotProps.data[col.field]) }}
                    </template>
                    <span v-else class="text-gray-900 dark:text-gray-100">
                        {{ slotProps.data[col.field] ?? '—' }}
                    </span>
                </template>

                <template #filter="{ filterModel, filterCallback }" v-if="col.filterable">
                    <div class="p-fluid">
                        <InputText
                            v-if="!col.filterType || col.filterType === 'text'"
                            v-model="filterModel.value"
                            type="text"
                            @input="filterCallback()"
                            class="p-column-filter text-sm h-9"
                            :placeholder="col.filterPlaceholder ?? 'Search...'"
                        />
                        <div v-else-if="col.filterType === 'boolean'" class="flex flex-col gap-2 py-1">
                            <div class="flex items-center">
                                <RadioButton
                                    v-model="filterModel.value"
                                    :input-id="`${String(col.field)}-true`"
                                    :value="true"
                                    @change="filterCallback()"
                                />
                                <label :for="`${String(col.field)}-true`" class="ml-2 text-sm">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <RadioButton
                                    v-model="filterModel.value"
                                    :input-id="`${String(col.field)}-false`"
                                    :value="false"
                                    @change="filterCallback()"
                                />
                                <label :for="`${String(col.field)}-false`" class="ml-2 text-sm">No</label>
                            </div>
                        </div>
                        <InputNumber
                            v-else-if="col.filterType === 'number'"
                            v-model="filterModel.value"
                            class="p-column-filter"
                            @input="filterCallback()"
                        />
                        <DatePicker
                            v-else-if="col.filterType === 'date'"
                            v-model="filterModel.value"
                            class="p-column-filter"
                            date-format="yy-mm-dd"
                            @date-select="filterCallback()"
                        />
                        <Select
                            v-else-if="col.filterType === 'dropdown' || col.filterType === 'multiselect'"
                            v-model="filterModel.value"
                            :options="col.filterOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="col.filterPlaceholder ?? 'Select...'"
                            class="p-column-filter text-sm"
                            :show-clear="true"
                            @change="filterCallback()"
                        />
                        <span v-else class="text-xs text-gray-500">—</span>
                    </div>
                </template>
            </Column>

            <Column
                v-if="actions && actions.length"
                header="Actions"
                header-style="width: 3.5rem"
                body-style="text-align: center; width: 3.5rem"
                :sortable="false"
                frozen
            >
                <template #body="slotProps">
                    <ActionsDropdown :actions="actions" :row="slotProps.data" />
                </template>
            </Column>

            <template #empty>
                <slot name="empty">
                    <DataTableEmptyState />
                </slot>
            </template>
            <template #loading>
                <DataTableLoadingState />
            </template>
            <template #paginatorend>
                <Button label="Export" icon="pi pi-file-excel" severity="contrast" @click="(e) => toggleExportMenu(e)" />
                <Menu
                    :model="[
                        { label: 'Export Visible', icon: 'pi pi-eye', command: handleExportVisible },
                        { label: 'Export All', icon: 'pi pi-globe', command: handleExportAll },
                    ]"
                    popup
                    ref="exportMenu"
                />
            </template>
        </DataTable>
    </div>
</template>

<style scoped lang="postcss">
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    @apply py-3.5 px-4 text-sm align-middle;
}
:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider py-3 px-4;
}
:deep(.p-column-filter) {
    @apply w-full;
}
</style>
