<script setup lang="ts">
/**
 * Settings/System/DynamicEnums/Index.vue
 *
 * Definition catalogue for application-owned Dynamic Enums.
 * Keys are fixed (seeded); administrators configure values and presentation
 * on the detail (Show) page.
 *
 * Uses AdvancedDataTable + HasTableQuery for server-side catalogue querying
 * (filter, sort, global search) consistent with other Settings list pages.
 */

import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import type { ColumnDefinition, TableAction } from '@/types/datatables'

interface DefinitionRow {
  id: string
  key: string
  label: string
  description: string | null
}

const props = defineProps<{
  initialData: DefinitionRow[]
  totalRecords: number
  columns: ColumnDefinition<DefinitionRow>[]
  globalFilterables: string[]
  canManage: boolean
  canManageGlobals: boolean
}>()

const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

const { enhancedColumns } = useEnhancedColumns<DefinitionRow>(
  props.columns,
  {
    key: {
      header: 'Key',
      render: (row) => ({
        template: 'span',
        text: row.key,
        class: 'font-mono text-xs text-surface-600 dark:text-surface-400',
      }),
    },
    label: {
      header: 'Label',
      render: (row) => ({
        template: 'span',
        text: row.label,
        class: 'font-medium text-gray-900 dark:text-gray-100',
      }),
    },
    description: {
      header: 'Description',
      render: (row) => ({
        template: 'span',
        text: row.description || '—',
        class: 'text-surface-600 dark:text-surface-400',
      }),
    },
  },
)

const rowActions = computed<TableAction<DefinitionRow>[]>(() => [
  {
    label: 'Configure',
    icon: 'pi pi-cog',
    handler: (row) => {
      router.visit(route('settings.system.dynamic-enums.show', row.key))
    },
  },
])
</script>

<template>
  <AuthenticatedLayout
    title="Dynamic Enums"
    :crumb="[
      { label: 'Settings' },
      { label: 'System' },
      { label: 'Dynamic Enums' },
    ]"
  >
    <div class="mb-4">
      <p class="text-sm text-surface-500">
        Application-defined vocabularies. Keys are fixed; administrators configure values and presentation.
      </p>
    </div>

    <AdvancedDataTable
      ref="tableRef"
      :endpoint="route('settings.system.dynamic-enums.index')"
      :initial-data="props.initialData"
      :total-records="props.totalRecords"
      :columns="enhancedColumns"
      :global-filter-fields="props.globalFilterables"
      :actions="rowActions"
      data-property="data"
    />
  </AuthenticatedLayout>
</template>
