<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '../Partials/SettingsLayout.vue'
import type { BulkAction, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'

const props = defineProps<{
    classLevels: { data: any[]; totalRecords?: number }
    columns: any[]
    endpoint: string
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref()

const columns = computed(() => props.columns ?? [])
const endpoint = computed(() => props.endpoint)
const initialParams = computed(() => ({}))

const actions = computed<TableAction<any>[]>(() => [
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('class-levels.destroy', [row.id]),
        show: () => hasPermission('class-levels.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'class-levels.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('class-levels.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="Class Levels">
        <SettingsLayout>
            <template #main>
                <AdvancedDataTable
                    ref="tableRef"
                    :endpoint="endpoint"
                    :columns="columns"
                    :actions="actions"
                    :bulk-actions="bulkActions"
                    :initial-params="initialParams"
                    :initial-data="classLevels.data"
                />
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
