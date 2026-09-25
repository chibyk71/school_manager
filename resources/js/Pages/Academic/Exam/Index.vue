<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'

const props = defineProps<{
    columns: ColumnDefinition<any>[]
    currentTerm?: { id: string | number }
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref()

const columns = computed(() => props.columns ?? [])
const currentTerm = computed(() => props.currentTerm)

const actions = computed<TableAction<any>[]>(() => [
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('exams.destroy', [row.id]),
        show: () => hasPermission('exams.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'exams.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('exams.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="Exams">
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('exams.index')"
            :columns="columns"
            :actions="actions"
            :bulk-actions="bulkActions"
            :initial-params="{
                term_id: currentTerm?.id,
            }"
        />
    </AuthenticatedLayout>
</template>
