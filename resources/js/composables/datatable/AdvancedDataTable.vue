<!-- resources/js/Components/shared/AdvancedDataTable.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed } from 'vue'
import { useDataTable } from './useDataTable'
import DataTableHeader from './DataTableHeader.vue'
import DataTableEmptyState from './DataTableEmptyState.vue'
import DataTableLoadingState from './DataTableLoadingState.vue'
import RenderCell from './RenderCell.vue'

import { Column, DataTable, DatePicker, InputNumber, InputText, RadioButton, Select } from 'primevue'
import type { BulkAction, ColumnDefinition } from '@/types/datatables'

import { formatDate } from '@/helpers'

const props = defineProps<{
    /** API endpoint (Inertia or Axios) */
    endpoint: string

    /** Initial data (for server-side rendered pages) */
    initialData?: T[]

    /** Extra static params sent on every request */
    initialParams?: Record<string, any>

    /** Total records from Inertia props (optional) */
    totalRecords?: number

    /** Column definitions – fully typed */
    columns: ColumnDefinition<T>[]

    /** Bulk actions configuration */
    bulkActions?: BulkAction[]

    /** Fields used for global search */
    globalFilterFields?: string[]

    /** Enable virtual scrolling virtualization (PrimeVue 4.1+) */
    virtualScroller?: boolean
}>()

const emit = defineEmits<{
    (e: 'bulk-action', action: string, selected: T[]): void
}>()

const {
    rows,
    totalRecords: totalFromStore,
    loading,
    perPage,
    currentPage,
    selectedRows,
    hiddenColumns,
    filters,
    visibleColumns,
    onPage,
    onSort,
    refresh,
} = useDataTable<T>(props.endpoint, props.columns, {
    initialParams: props.initialParams,
    initialData: props.initialData,
    bulkActions: props.bulkActions,
})

console.log(rows.value);


// wrapper to accept PrimeVue's DataTableSortEvent (sortField may be undefined) and forward to composable
const onSortHandler = (event: any) => {
    onSort(event as { sortField: string; sortOrder: 1 | -1; multiSortMeta: any[] })
}

// Defensive defaults + reactivity safety
const safeBulkActions = computed(() => props.bulkActions ?? [])
const safeGlobalFilterFields = computed(() => props.globalFilterFields ?? [])

// Use totalRecords from Inertia props if provided (SSR), otherwise from store
const totalRecords = computed(() => props.totalRecords ?? totalFromStore.value)

// Optional: expose refresh to parent
defineExpose({ refresh })
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <DataTableHeader :selected-rows="selectedRows" :bulk-actions="safeBulkActions" :columns="columns"
            v-model:hidden-columns="hiddenColumns" v-model:global-search="filters.global.value" @refresh="refresh"
            @bulk-action="emit('bulk-action', $event[0], selectedRows)" />

        <!-- PrimeVue DataTable -->
        <DataTable :value="rows" :loading="loading" :paginator="true" :rows="perPage" :totalRecords="totalRecords"
            :virtual-scroller-options="virtualScroller ? { itemSize: 56 } : undefined" @page="onPage"
            @sort="onSortHandler" paginator-template="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink" current-page-report-template="{first} - {last} of {totalRecords}" v-model:selection="selectedRows" data-key="id" responsive-layout="scroll" class="p-datatable-sm" striped-rows removable-sort scrollable :scroll-height="virtualScroller ? '600px' : undefined" :global-filter-fields="safeGlobalFilterFields" filter-display="menu" :filters="filters">
            <!-- Selection column -->
            <Column selection-mode="multiple" header-style="width: 3.5rem" body-style="text-align: center" />

            <!-- Dynamic columns -->
            <Column v-for="col in visibleColumns" :key="col.field" :field="col.field as string" :header="col.header"
                :sortable="col.sortable !== false" :header-class="col.headerClass" :body-class="col.bodyClass"
                :style="col.width ? { width: col.width } : undefined" :frozen="col.frozen"
                v-memo="[col.field, col.header, col.sortable, col.bodyClass]">
                <!-- BODY CELL -->
                <template #body="slotProps">
                    <!-- 1. Custom render via column.render() – highest priority -->
                    <RenderCell v-if="col.render" :render-result="col.render(slotProps.data)" :row="slotProps.data" />

                    <!-- 2. Simple formatter fallback -->
                    <template v-else-if="col.formatter">
                        {{ col.formatter(slotProps.data[col.field], slotProps.data) }}
                    </template>

                    <!-- 3. Special handling for common types (boolean, date) -->
                    <template v-else-if="col.filterType === 'boolean'">
                        <i :class="slotProps.data[col.field]
                            ? 'pi pi-check-circle text-green-600'
                            : 'pi pi-times-circle text-red-600'" aria-hidden="true" />
                    </template>

                    <template v-else-if="col.filterType === 'date' && slotProps.data[col.field]">
                        {{ formatDate(slotProps.data[col.field]) }}
                    </template>

                    <!-- 4. Default safe display -->
                    <span v-else class="text-gray-900 dark:text-gray-100">
                        {{ slotProps.data[col.field] ?? '—' }}
                    </span>
                </template>

                <!-- FILTER -->
                <template #filter="{ filterModel, filterCallback }" v-if="col.filterable">
                    <div class="p-fluid">
                        <!-- Text -->
                        <InputText v-if="!col.filterType || col.filterType === 'text'" v-model="filterModel.value"
                            type="text" @input="filterCallback" class="p-column-filter text-sm h-9"
                            :placeholder="col.filterPlaceholder ?? 'Search...'"
                            :aria-label="`Filter by ${col.header}`" />

                        <!-- Boolean -->
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

                        <!-- Date -->
                        <DatePicker v-else-if="col.filterType === 'date'" v-model="filterModel.value"
                            date-format="dd/mm/yy" :placeholder="col.filterPlaceholder ?? 'dd/mm/yyyy'"
                            class="p-column-filter text-sm" :show-button-bar="true" @date-select="filterCallback"
                            @clear-click="filterCallback" :aria-label="`Filter date for ${col.header}`" />

                        <!-- Number -->
                        <InputNumber v-else-if="col.filterType === 'number'" v-model="filterModel.value"
                            @input="filterCallback" class="p-column-filter" :min-fraction-digits="0"
                            :placeholder="col.filterPlaceholder ?? 'Enter number'"
                            :aria-label="`Filter number for ${col.header}`" />

                        <!-- Dropdown -->
                        <Select v-else-if="col.filterType === 'dropdown' && col.filterOptions"
                            v-model="filterModel.value" :options="col.filterOptions" option-label="label"
                            option-value="value" :placeholder="col.filterPlaceholder ?? 'Select...'"
                            class="p-column-filter text-sm" :show-clear="true" @change="filterCallback"
                            :aria-label="`Filter by ${col.header}`" />

                        <!-- Fallback -->
                        <span v-else class="text-xs text-gray-500 select-none">—</span>
                    </div>
                </template>
            </Column>

            <!-- Empty & Loading States -->
            <template #empty>
                <DataTableEmptyState />
            </template>
            <template #loading>
                <DataTableLoadingState />
            </template>
        </DataTable>
    </div>
</template>

<style scoped lang="postcss">
/* Table styling – clean & consistent */
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    @apply py-3.5 px-4 text-sm align-middle;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider py-3 px-4;
}

:deep(.p-column-filter) {
    @apply w-full;
}

/* Virtual scroller smooth scrolling */
:deep(.p-virtualscroller-content) {
    @apply will-change-transform;
}
</style>
