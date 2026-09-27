<script setup lang="ts">
/**
 * Settings/System/DynamicEnums/Index.vue — Phase 7
 *
 * Tenant context: application-owned definition catalogue (DataTable).
 * School context: effective school configuration — inherited, overridden, and
 * school-created options appear once per value (no duplicate tenant/school rows).
 */

import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import { Tag } from 'primevue'
import type { ColumnDefinition, TableAction } from '@/types/datatables'
import type {
  DynamicEnumDefinition,
  DynamicEnumEffectiveCatalogueRow,
} from '@/types/dynamic-enums'

const props = defineProps<{
  scope: 'tenant' | 'school' | string
  school_id?: string | null
  data: DynamicEnumDefinition[] | DynamicEnumEffectiveCatalogueRow[]
  columns: ColumnDefinition<DynamicEnumDefinition>[]
  meta?: {
    currentPage: number
    perPage: number
    total: number
    lastPage: number
  }
  canManage: boolean
  hasSchoolContext: boolean
}>()

const isSchoolScope = computed(() => props.scope === 'school' || props.hasSchoolContext)

const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

const initialTableResponse = computed(() => {
  if (isSchoolScope.value) return null
  if (!props.meta || !props.columns?.length) return null
  return {
    data: props.data ?? [],
    columns: props.columns as any,
    meta: props.meta,
  }
})

const { enhancedColumns } = useEnhancedColumns<DynamicEnumDefinition>(
  props.columns ?? [],
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

const rowActions = computed<TableAction<DynamicEnumDefinition>[]>(() => [
  {
    label: 'Configure',
    icon: 'pi pi-cog',
    handler: (row) => {
      router.visit(route('settings.system.dynamic-enums.show', row.key))
    },
  },
])

const schoolRows = computed(() =>
  isSchoolScope.value ? (props.data as DynamicEnumEffectiveCatalogueRow[]) : []
)

function sourceSeverity(source: string): string {
  if (source === 'overridden') return 'warn'
  if (source === 'school-created') return 'success'
  return 'secondary'
}

function configure(key: string) {
  router.visit(route('settings.system.dynamic-enums.show', key))
}
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
        <template v-if="isSchoolScope">
          Effective configuration for the current school. Inherited, overridden, and
          school-created options appear once. Open a definition to manage school overlays.
        </template>
        <template v-else>
          Tenant configuration. Keys are fixed; administrators configure values and presentation.
        </template>
      </p>
      <p v-if="isSchoolScope" class="mt-1 text-xs text-surface-400">
        Scope: school · effective catalogue
      </p>
      <p v-else class="mt-1 text-xs text-surface-400">
        Scope: tenant · definition catalogue
      </p>
    </div>

    <!-- Tenant: AdvancedDataTable definition catalogue -->
    <AdvancedDataTable
      v-if="!isSchoolScope"
      ref="tableRef"
      :endpoint="route('settings.system.dynamic-enums.index')"
      :initial-response="initialTableResponse"
      :columns="enhancedColumns"
      :actions="rowActions"
    />

    <!-- School: effective configuration (options once per value with source) -->
    <div v-else class="space-y-4">
      <div
        v-for="row in schoolRows"
        :key="row.key"
        class="rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 overflow-hidden"
      >
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div>
            <div class="font-medium text-gray-900 dark:text-gray-100">{{ row.label }}</div>
            <div class="font-mono text-xs text-surface-500">{{ row.key }}</div>
            <div v-if="row.description" class="text-sm text-surface-500 mt-0.5">{{ row.description }}</div>
          </div>
          <button
            type="button"
            class="text-sm text-primary-600 hover:underline"
            @click="configure(row.key)"
          >
            Configure
          </button>
        </div>
        <div class="px-4 py-2 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-surface-500">
                <th class="py-1 pr-3 font-medium">Value</th>
                <th class="py-1 pr-3 font-medium">Label</th>
                <th class="py-1 pr-3 font-medium">Source</th>
                <th class="py-1 pr-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="opt in row.options"
                :key="opt.value"
                class="border-t border-surface-100 dark:border-surface-800"
              >
                <td class="py-1.5 pr-3 font-mono text-xs">{{ opt.value }}</td>
                <td class="py-1.5 pr-3">{{ opt.label }}</td>
                <td class="py-1.5 pr-3">
                  <Tag :value="opt.source" :severity="sourceSeverity(opt.source)" />
                </td>
                <td class="py-1.5 pr-3">
                  <Tag v-if="opt.is_required" value="required" severity="danger" class="mr-1" />
                  <Tag
                    :value="opt.is_active ? 'active' : 'inactive'"
                    :severity="opt.is_active ? 'success' : 'secondary'"
                  />
                </td>
              </tr>
              <tr v-if="!row.options?.length">
                <td colspan="4" class="py-3 text-surface-500">No effective options.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p v-if="!schoolRows.length" class="text-sm text-surface-500">No Dynamic Enum definitions configured.</p>
    </div>
  </AuthenticatedLayout>
</template>
