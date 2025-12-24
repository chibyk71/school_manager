<!-- resources/js/Components/students/StudentDataTable.vue -->
<script setup lang="ts">// Your enhanced version
import type { Student } from '@/types/student'
import { formatDate } from '@/helpers' // Assumed
import { ColumnDefinition } from '@/types/datatables';
import { useDataTable } from '@/composables/useDataTable';
import DataTableHeader from '@/Components/datatable/DataTableHeader.vue';
import { Column, DataTable } from 'primevue';
import DataTableEmptyState from '@/Components/datatable/DataTableEmptyState.vue';
import DataTableLoadingState from '@/Components/datatable/DataTableLoadingState.vue';

const props = defineProps<{
  endpoint: string
  columns: ColumnDefinition<Student>[]
  bulkActions?: any[]
  initialParams?: Record<string, any>
  initialData?: Student[] // For Inertia pre-load
  totalRecords?: number
}>()

const {
  rows,
  totalRecords: total, // Rename to avoid conflict
  loading,
  error,
  perPage,
  currentPage,
  selectedRows,
  hiddenColumns,
  filters,
  visibleColumns,
  onPage,
  onSort,
  refresh,
  performBulkAction, // From enhanced
  exportData // From enhanced
} = useDataTable<Student>(props.endpoint, props.columns, {
  initialParams: props.initialParams,
  bulkActions: props.bulkActions
})

// Pre-load from Inertia if provided
if (props.initialData) {
  rows.value = props.initialData
  total.value = props.totalRecords ?? 0
}
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <DataTableHeader 
      :selected-rows="selectedRows" 
      :bulk-actions="bulkActions" 
      :columns="columns"
      v-model:hidden-columns="hiddenColumns"
      v-model:global-search="filters.global.value"
      @refresh="refresh" 
    />

    <div v-if="error" class="p-4 text-red-600 bg-red-50 dark:bg-red-900/50">
      {{ error }} <Button label="Retry" text @click="refresh" />
    </div>

    <DataTable 
      :value="rows" 
      :loading="loading" 
      :paginator="true" 
      :rows="perPage" 
      :totalRecords="total"
      :first="(currentPage - 1) * perPage" 
      :rowsPerPageOptions="[10, 20, 50, 100]"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} - {last} of {totalRecords}" 
      v-model:selection="selectedRows" 
      dataKey="id"
      responsiveLayout="scroll" 
      class="p-datatable-sm" 
      @page="onPage" 
      @sort="onSort"
    >
      <Column selectionMode="multiple" headerStyle="width: 3rem" />

      <Column v-for="col in visibleColumns" :key="col.field" :field="col.field as string" :header="col.header"
        :sortable="col.sortable !== false" :style="col.style" :class="col.headerClass">
        <template #body="slotProps">
          <slot :name="`${col.field}-body`" v-bind="slotProps">
            <!-- Smart rendering (existing logic) -->
            <template v-if="col.filterType === 'boolean'">
              <i :class="slotProps.data[col.field] ? 'pi pi-check-circle text-green-600' : 'pi pi-times-circle text-red-600'" />
            </template>
            <template v-else-if="col.filterType === 'date'">
              {{ formatDate(slotProps.data[col.field]) }}
            </template>
            <template v-else-if="col.render">
              <component :is="col.render(slotProps.data)" />
            </template>
            <span v-else>{{ slotProps.data[col.field] }}</span>
          </slot>
        </template>
      </Column>

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
:deep(.p-datatable .p-datatable-tbody > tr > td) {
  @apply py-3 px-4 text-sm;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  @apply bg-gray-50 dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider;
}
</style>