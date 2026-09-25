<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import { modals } from '@/helpers'

const props = defineProps<{
    initialData: any[]
    totalRecords: number
    columns: ColumnDefinition<any>[]
    globalFilterables: string[]
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref()

const enhancedColumns = computed(() => props.columns ?? [])

const rowActions = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => modals.open('section-form', { section: row }),
        show: () => hasPermission('sections.update'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('settings.school.sections.destroy', [row.id]),
        show: () => hasPermission('sections.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'settings.school.sections.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('sections.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="School Sections">
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('settings.school.sections.index')"
            :initial-data="props.initialData"
            :columns="enhancedColumns"
            :actions="rowActions"
            :bulk-actions="bulkActions"
        />
    </AuthenticatedLayout>
</template>
