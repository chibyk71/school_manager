<script setup lang="ts">
import { computed, markRaw } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { usePermissions } from '@/composables/usePermissions'
import ToggleSwitch from 'primevue/toggleswitch'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { useTrashedToggle } from '@/composables/useTrashedToggle'
import { useDeleteResource } from '@/composables/useDelete'

const toast = useToast()
const { hasPermission } = usePermissions()
const { restoreResource } = useRestoreResource()
const { deleteResource } = useDeleteResource()

const props = defineProps<{
    data: any[]
    totalRecords: number
    columns: ColumnDefinition<any>[]
    globalFilterables: string[]
}>()

const enhancedColumns = computed(() => props.columns ?? [])

const schoolActions = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => router.visit(route('schools.edit', row.id)),
        show: () => hasPermission('schools.update'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('schools.destroy', [row.id]),
        show: () => hasPermission('schools.delete'),
    },
])

const schoolBulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) => deleteResource('schools.destroy', selected.map((s: any) => s.id)),
        visible: () => hasPermission('schools.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout title="Schools">
        <div class="space-y-6">
            <AdvancedDataTable
                endpoint="settings/schools"
                :columns="enhancedColumns"
                :bulk-actions="schoolBulkActions"
                :initial-data="props.data"
                :actions="schoolActions"
            />
        </div>
    </AuthenticatedLayout>
</template>
