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

const toast = useToast()
const { hasPermission } = usePermissions()

const props = defineProps<{
    data: any
    roles: Array<{ id: string; display_name: string }>
    totalRecords: number
    columns: ColumnDefinition<any>[]
    globalFilterables: string[]
}>()

const { deleteResource } = useDeleteResource()
const showTrashed = ref(false)

const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = Array.isArray(props.columns) ? [...props.columns] : []
    const upsert = (field: string, newCol: Partial<ColumnDefinition<any>>) => {
        const index = cols.findIndex((c) => c.field === field)
        if (index >= 0) {
            cols[index] = { ...cols[index], ...newCol }
        } else {
            cols.push({ field, header: field, ...newCol } as ColumnDefinition<any>)
        }
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
    },
])

const handleBulkAction = async (action: string) => {
    /* reserved */
}

const departmentsArray = computed(() => {
    const d = props.data
    if (Array.isArray(d)) return d
    return d?.data ?? []
})
</script>

<template>
    <AuthenticatedLayout title="Departments">
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
