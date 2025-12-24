<!-- resources/js/composables/datatable/AdvancedDataTable.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, ref } from 'vue'

// Core composable that handles all data fetching, hybrid mode, windowed prefetching, sorting, filtering, etc.
import { useDataTable } from '../../composables/useDataTable'

// UI sub-components
import DataTableHeader from './DataTableHeader.vue'          // Header with bulk actions, column visibility, global search, refresh, export
import DataTableEmptyState from './DataTableEmptyState.vue'    // Shown when no rows match filters
import DataTableLoadingState from './DataTableLoadingState.vue' // Skeleton loader during fetches
import RenderCell from './RenderCell.vue'                      // Handles complex cell rendering (templates, components, etc.)

// PrimeVue components used in the table and filters
import {
    Button,
    Column,
    DataTable,
    DatePicker,
    InputNumber,
    InputText,
    RadioButton,
    Select,
} from 'primevue'

// Type imports for strong typing
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'

// Helper for consistent date formatting across the app
import { formatDate } from '@/helpers'
import ActionsDropdown from './ActionsDropdown.vue'

/**
 * Props definition – everything the parent page needs to pass
 */
const props = defineProps<{
    /** API endpoint for data (e.g., /api/users) */
    endpoint: string

    /** Optional initial data for SSR (Inertia passes this) */
    initialData?: T[]

    /** Static params sent on every request (e.g., { tenant_id: 5 }) */
    initialParams?: Record<string, any>

    /** Optional totalRecords from Inertia SSR – avoids extra count query */
    totalRecords?: number

    /** Full column configuration – the heart of the table */
    columns: ColumnDefinition<T>[]

    /** Bulk actions shown in header when rows are selected */
    bulkActions?: BulkAction[]

    /** Fields included in global search (PrimeVue requirement) */
    globalFilterFields?: string[]

    /** Enable PrimeVue virtual scrolling for very tall tables */
    virtualScroller?: boolean

    /** Property to access the rows array from the json result */
    dataProperty?: string

    /** Optional actions for each row (e.g., edit, delete) */
    actions?: TableAction<T>[]

}>()

/**
 * Emits – currently only bulk-action for parent handling (e.g., open confirmation modal)
 */
const emit = defineEmits<{
    (e: 'bulk-action', action: string, selected: T[]): void
}>()

/**
 * Reference to the PrimeVue DataTable instance.
 * Useful in the future for programmatic actions like exportCSV() if we enhance client-side export.
 */
const tableRef = ref<InstanceType<typeof DataTable> | null>(null)

/**
 * Destructure everything from our enhanced useDataTable composable
 */
const {
    dtRef,                  // Reference to DataTable instance
    tableData,                  // Current page rows (shallowRef – reactive but shallow for performance)
    totalRecords: totalFromStore, // Total count from API / client-side calculation
    loading,              // Global loading state for spinner/skeleton
    perPage,              // Rows per page (controlled by paginator dropdown)
    currentPage,          // Not directly used here but available
    selectedRows,         // v-model for row selection
    hiddenColumns,        // Tracks user-hidden columns
    filters,              // PrimeVue filter model (global + per-column)
    visibleColumns,       // Computed list of columns that are not hidden
    onPage,               // Pagination handler (client-side slice or server-side fetch)
    onSort,               // Sort handler (client-side sort or server-side)
    onFilter,             // Filter handler (not directly bound – PrimeVue calls it via filterCallback)
    refresh,              // Manual refresh (clears selection + refetches)
    isClientSide,         // Critical flag: true → full dataset in memory, false → server-side with window prefetching
    exportData,           // Unified export function (client-side CSV or backend blob)
} = useDataTable<T>(props.endpoint, props.columns, {
    initialParams: props.initialParams,
    initialData: props.initialData,
    bulkActions: props.bulkActions,
    dataProperty: props.dataProperty,
})

/**
 * Wrapper for PrimeVue's sort event – type safety + forward to composable
 */
const onSortHandler = (event: any) => {
    onSort(event as { sortField?: string; sortOrder?: 1 | -1; multiSortMeta?: any[] })
}

/**
 * Defensive computed props – ensures we never pass undefined to PrimeVue
 */
const safeBulkActions = computed(() => props.bulkActions ?? [])
const safeGlobalFilterFields = computed(() => props.globalFilterFields ?? [])

/**
 * Total records priority:
 * 1. Inertia-provided prop (SSR – fastest)
 * 2. Fallback to composable's count
 */
const totalRecords = computed(() => props.totalRecords ?? totalFromStore.value)

/**
 * Expose useful methods to parent components (e.g., Page.vue can call table.refresh())
 */
defineExpose({ refresh, exportData })

/**
 * Export handlers – passed to DataTableHeader so buttons can trigger correct export mode
 * - Visible: only current page (fast)
 * - All: entire dataset (backend for large tables, client-side for small)
 */
const handleExportVisible = () => exportData(false, true)
const handleExportAll = () => exportData(true, false)
</script>

<template>
    <!-- Main container with consistent styling across light/dark mode -->
    <div
        class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!--
      Custom header component:
      - Shows selected count
      - Bulk action buttons
      - Column visibility toggle
      - Global search input
      - Refresh button
      - Export buttons (visible/all)
    -->
        <DataTableHeader :selected-rows="selectedRows" :bulk-actions="safeBulkActions" :columns="columns"
            v-model:hidden-columns="hiddenColumns" v-model:global-search="filters.global.value" @refresh="refresh"
            @bulk-action="emit('bulk-action', $event[0], selectedRows)" @export-visible="handleExportVisible"
            @export-all="handleExportAll" />

        <!--
      PrimeVue DataTable – the core table component
      Key optimizations:
      - :lazy="!isClientSide" → disables lazy loading when we have all data (client-side mode)
      - :value="rows" → direct shallowRef binding (no unnecessary spread)
      - virtualScroller → renders only visible rows for huge windows
    -->
        <DataTable :ref="(el) => (dtRef = el)" :value="tableData" :loading="loading" :lazy="!isClientSide" paginator
            :rows="perPage" :total-records="totalRecords"
            :virtual-scroller-options="virtualScroller ? { itemSize: 56 } : undefined" @page="onPage"
            @sort="onSortHandler" :rowsPerPageOptions="[10, 20, 50, 100, 150, 200]"
            paginator-template="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
            current-page-report-template="{first} - {last} of {totalRecords}" v-model:selection="selectedRows"
            data-key="id" class="p-datatable-sm !rounded-none" striped-rows removable-sort
            scrollable :scroll-height="virtualScroller ? '600px' : undefined"
            :global-filter-fields="safeGlobalFilterFields" filter-display="menu" :filters="filters">
            <!-- Checkbox column for multi-selection -->
            <Column selection-mode="multiple" header-style="width: 3.5rem" body-style="text-align: center" />

            <!-- Dynamically rendered columns based on visibleColumns -->
            <Column v-for="col in visibleColumns" :key="col.field" :field="col.field as string" :header="col.header"
                :sortable="col.sortable !== false" :header-class="col.headerClass" :body-class="col.bodyClass"
                :style="col.width ? { width: col.width } : undefined" :frozen="col.frozen"
                v-memo="[col.field, col.header, col.sortable, col.bodyClass]">
                <!-- BODY CELL RENDERING LOGIC (priority order) -->
                <template #body="slotProps">
                    <!-- 1. Highest priority: custom render function (full control) -->
                    <RenderCell v-if="col.render" :render-result="col.render(slotProps.data)" :row="slotProps.data" />

                    <!-- 2. Simple formatter (e.g., currency, uppercase) -->
                    <template v-else-if="col.formatter">
                        {{ col.formatter(slotProps.data[col.field], slotProps.data) }}
                    </template>

                    <!-- 3. Special built-in displays -->
                    <template v-else-if="col.filterType === 'boolean'">
                        <i :class="slotProps.data[col.field] ? 'pi pi-check-circle text-green-600' : 'pi pi-times-circle text-red-600'"
                            aria-hidden="true" />
                    </template>

                    <template v-else-if="col.filterType === 'date' && slotProps.data[col.field]">
                        {{ formatDate(slotProps.data[col.field]) }}
                    </template>

                    <!-- 4. Fallback: safe raw display with dash for null/undefined -->
                    <span v-else class="text-gray-900 dark:text-gray-100">
                        {{ slotProps.data[col.field] ?? '—' }}
                    </span>
                </template>

                <!-- COLUMN FILTER UI (only if column is filterable) -->
                <template #filter="{ filterModel, filterCallback }" v-if="col.filterable">
                    <div class="p-fluid">
                        <!-- Text search -->
                        <InputText v-if="!col.filterType || col.filterType === 'text'" v-model="filterModel.value"
                            type="text" @input="filterCallback" class="p-column-filter text-sm h-9"
                            :placeholder="col.filterPlaceholder ?? 'Search...'"
                            :aria-label="`Filter by ${col.header}`" />

                        <!-- Boolean tri-state (Yes/No/All) -->
                        <div v-else-if="col.filterType === 'boolean'" class="flex flex-col gap-2 py-1">
                            <div class="flex items-center">
                                <RadioButton v-model="filterModel.value" :input-id="`${String(col.field)}-true`"
                                    :value="true" @change="filterCallback" />
                                <label :for="`${String(col.field)}-true`" class="ml-2 text-sm">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <RadioButton v-model="filterModel.value" :input-id="`${String(col.field)}-false`"
                                    :value="false" @change="filterCallback" />
                                <label :for="`${String(col.field)}-false`" class="ml-2 text-sm">No</label>
                            </div>
                            <div class="flex items-center">
                                <RadioButton v-model="filterModel.value" :input-id="`${String(col.field)}-null`"
                                    :value="null" @change="filterCallback" />
                                <label :for="`${String(col.field)}-null`" class="ml-2 text-sm">All</label>
                            </div>
                        </div>

                        <!-- Date picker -->
                        <DatePicker v-else-if="col.filterType === 'date'" v-model="filterModel.value"
                            date-format="dd/mm/yy" :placeholder="col.filterPlaceholder ?? 'dd/mm/yyyy'"
                            class="p-column-filter text-sm" :show-button-bar="true" @date-select="filterCallback"
                            @clear-click="filterCallback" :aria-label="`Filter date for ${col.header}`" />

                        <!-- Number input -->
                        <InputNumber v-else-if="col.filterType === 'number'" v-model="filterModel.value"
                            @input="filterCallback" class="p-column-filter" :min-fraction-digits="0"
                            :placeholder="col.filterPlaceholder ?? 'Enter number'"
                            :aria-label="`Filter number for ${col.header}`" />

                        <!-- Dropdown / Multiselect (single select here) -->
                        <Select v-else-if="col.filterType === 'dropdown' && col.filterOptions"
                            v-model="filterModel.value" :options="col.filterOptions" option-label="label"
                            option-value="value" :placeholder="col.filterPlaceholder ?? 'Select...'"
                            class="p-column-filter text-sm" :show-clear="true" @change="filterCallback"
                            :aria-label="`Filter by ${col.header}`" />

                        <!-- Fallback when filter type is unsupported -->
                        <span v-else class="text-xs text-gray-500 select-none">—</span>
                    </div>
                </template>
            </Column>

            <!-- Actions column if any actions are defined -->
            <Column v-if="actions && actions.length" header="Actions" header-style="width: 3.5rem" body-style="text-align: center; width: 3.5rem;" :sortable="false" frozen>
                <template #body="slotProps">
                    <ActionsDropdown :actions="actions" :row="slotProps.data" />
                </template>
            </Column>

            <!-- Empty state when no rows match current filters -->
            <template #empty>
                <DataTableEmptyState />
            </template>

            <!-- Loading overlay with skeleton rows -->
            <template #loading>
                <DataTableLoadingState />
            </template>
            <template #paginatorend>
                <Button label='Export' icon='pi pi-file-excel' severity="contrast" @click="()=> exportData(true)"></Button>
            </template>
        </DataTable>
    </div>
</template>

<style scoped lang="postcss">
/* Consistent cell and header padding + typography */
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    @apply py-3.5 px-4 text-sm align-middle;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider py-3 px-4;
}

/* Full-width column filters */
:deep(.p-column-filter) {
    @apply w-full;
}

/* Improve virtual scroller performance */
:deep(.p-virtualscroller-content) {
    @apply will-change-transform;
}
</style>
