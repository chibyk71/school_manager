<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'

const props = defineProps<{
    columns: ColumnDefinition<any>[]
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref()
const showTrashed = ref(false)

const enhancedColumns = computed(() => props.columns ?? [])
const initialData = computed(() => props.initialData ?? [])

const rowActions = computed<TableAction<any>[]>(() => [
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('settings.academic.subjects.destroy', [row.id]),
        show: () => hasPermission('subjects.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'settings.academic.subjects.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('subjects.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="Subjects">
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('settings.academic.subjects.index')"
            :columns="enhancedColumns"
            :bulk-actions="bulkActions"
            :actions="rowActions"
            :initial-params="showTrashed ? { with_trashed: true } : {}"
        />
    </AuthenticatedLayout>
</template>
