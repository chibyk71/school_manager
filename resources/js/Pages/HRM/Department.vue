<!--
  Department Index — full product page restored from master.
  Phase 5: AdvancedDataTable without legacy props (no total-records / global-filter-fields).
-->
<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { usePermissions } from '@/composables/usePermissions'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import DataTableActions from './Components/DataTableActions.vue'
import { modals } from '@/helpers'
import type { BulkAction, ColumnDefinition } from '@/types/datatables'
import axios from 'axios'
import { Button, Card, Chip } from 'primevue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DepartmentRoleChip from './Components/DepartmentRoleChip.vue'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'

const toast = useToast()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

const props = defineProps<{
    data: any
    roles: Array<{ id: string; display_name: string }>
    totalRecords: number
    columns: ColumnDefinition<any>[]
    globalFilterables: string[]
    stats?: { total?: number; active?: number; trashed?: number }
}>()

const showTrashed = ref(false)

const departmentsArray = computed(() => {
    const d = props.data
    if (Array.isArray(d)) return d
    return d?.data ?? []
})

const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = Array.isArray(props.columns) ? [...props.columns] : []
    const upsert = (field: string, newCol: Partial<ColumnDefinition<any>>) => {
        const index = cols.findIndex((c) => c.field === field)
        if (index >= 0) cols[index] = { ...cols[index], ...newCol }
        else cols.push({ field, header: field, ...newCol } as ColumnDefinition<any>)
    }
    upsert('role_names', {
        header: 'Assigned Roles',
        filterable: true,
        filterType: 'multiselect',
        render: (row: any) => ({
            component: markRaw(DepartmentRoleChip) as any,
            props: { roles: row.roles },
        }),
    })
    upsert('member_count', {
        header: 'Members',
        sortable: true,
        align: 'center',
        width: '120px',
        render: (row: any) => ({
            template: 'span',
            text: row?.member_count ?? 0,
            class: 'font-semibold',
        }),
    })
    cols.push({
        field: 'actions',
        header: 'Actions',
        sortable: false,
        filterable: false,
        frozen: true,
        align: 'right',
        width: '100px',
        bodyClass: 'text-right',
        render: (row: any) => ({
            component: markRaw(DataTableActions) as any,
            props: { row },
        }),
    })
    return cols
})

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: async (selected) => {
            await deleteResource(
                'departments.destroy',
                selected.map((s: any) => s.id),
            )
        },
        visible: () => hasPermission('departments.delete'),
    },
    {
        label: 'Restore Selected',
        icon: 'pi pi-refresh',
        severity: 'info',
        handler: async (selected) => {
            await restoreResource(
                'departments.restore',
                selected.map((s: any) => s.id),
            )
        },
        visible: () => showTrashed.value && hasPermission('departments.restore'),
    },
])

const handleBulkAction = async (_action: string) => {
    /* reserved for parent bulk handler wiring */
}

const openCreate = () => modals.open('department', { mode: 'create', roles: props.roles })
</script>

<template>
    <AuthenticatedLayout
        title="Departments"
        :buttons="[
            {
                label: 'Add Department',
                icon: 'pi pi-plus',
                onClick: openCreate,
                class: { hidden: !hasPermission('departments.create') },
            },
        ]"
    >
        <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-sm text-gray-500">Total</div>
                    <div class="text-2xl font-semibold">{{ stats.total ?? totalRecords ?? 0 }}</div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-sm text-gray-500">Active</div>
                    <div class="text-2xl font-semibold">{{ stats.active ?? '—' }}</div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-sm text-gray-500">Trashed</div>
                    <div class="text-2xl font-semibold">{{ stats.trashed ?? '—' }}</div>
                </template>
            </Card>
        </div>

        <AdvancedDataTable
            :endpoint="route('departments.index')"
            :initial-data="departmentsArray"
            :columns="enhancedColumns"
            :bulk-actions="bulkActions"
            @bulk-action="handleBulkAction"
            :initial-params="{ with_trashed: showTrashed }"
        />
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
:deep(.p-chip) {
    @apply text-xs;
}
</style>
