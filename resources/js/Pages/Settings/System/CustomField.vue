<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'

const props = defineProps<{
    data: any[]
    totalRecords?: number
    columns: ColumnDefinition<any>[]
    globalFilterables?: string[]
    selectedResource: string
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const selectedResource = computed(() => props.selectedResource)

const enhancedColumns = computed(() => props.columns ?? [])

const actionsButtons = computed<TableAction<any>[]>(() => [
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) =>
            deleteResource('settings.system.custom-fields.destroy', [row.id]),
        show: () => hasPermission('custom-fields.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'settings.system.custom-fields.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('custom-fields.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="Custom Fields">
        <AdvancedDataTable
            :endpoint="route('settings.system.custom-fields', { resource: selectedResource })"
            :columns="enhancedColumns"
            :initial-data="props.data"
            :initial-params="{ resource: selectedResource }"
            :actions="actionsButtons"
            :bulk-actions="bulkActions"
        >
            <template #empty>
                <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                    <p class="text-lg mb-4">
                        No custom fields defined for <strong>{{ selectedResource }}</strong> yet.
                    </p>
                </div>
            </template>
        </AdvancedDataTable>
    </AuthenticatedLayout>
</template>
