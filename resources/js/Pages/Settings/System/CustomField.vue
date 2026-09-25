<!--
  CustomField Index — restored from master.
  Phase 5: removed total-records, global-filter-fields, export-filename from AdvancedDataTable.
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import { useModal } from '@/composables/useModal'
import Select from 'primevue/select'
import Button from 'primevue/button'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'

const props = defineProps<{
    data: any[]
    totalRecords?: number
    columns: ColumnDefinition<any>[]
    globalFilterables?: string[]
    selectedResource: string
    resources?: Array<{ label: string; value: string }>
}>()

const toast = useToast()
const modal = useModal()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()

const selectedResource = ref(props.selectedResource)
const enhancedColumns = computed(() => props.columns ?? [])

watch(selectedResource, (resource) => {
    router.get(route('settings.system.custom-fields', { resource }), {}, { preserveState: true })
})

const openCreate = () => {
    modal.open('custom-field-form', { resource: selectedResource.value, field: null })
}

const openEdit = (row: any) => {
    modal.open('custom-field-form', { resource: selectedResource.value, field: row })
}

const handleReorder = async (event: any) => {
    try {
        await axios.patch(route('settings.system.custom-fields.order', { resource: selectedResource.value }), {
            order: event.value?.map((r: any) => r.id) ?? [],
        })
        toast.add({ severity: 'success', summary: 'Order updated' })
    } catch {
        toast.add({ severity: 'error', summary: 'Failed to reorder' })
    }
}

const actionsButtons = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => openEdit(row),
        show: () => hasPermission('custom-fields.update'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('settings.system.custom-fields.destroy', [row.id]),
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
    <AuthenticatedLayout
        title="Custom Fields"
        :buttons="[
            {
                label: 'New Field',
                icon: 'pi pi-plus',
                onClick: openCreate,
                class: { hidden: !hasPermission('custom-fields.create') },
            },
        ]"
    >
        <div class="mb-4 flex items-center gap-3">
            <label class="text-sm font-medium">Resource</label>
            <Select
                v-model="selectedResource"
                :options="resources ?? []"
                option-label="label"
                option-value="value"
                class="w-64"
            />
        </div>

        <AdvancedDataTable
            :endpoint="route('settings.system.custom-fields', { resource: selectedResource })"
            :columns="enhancedColumns"
            :initial-data="props.data"
            :initial-params="{ resource: selectedResource }"
            @row-reorder="handleReorder"
            data-key="id"
            :actions="actionsButtons"
            :bulk-actions="bulkActions"
        >
            <template #empty>
                <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                    <p class="text-lg mb-4">
                        No custom fields defined for <strong>{{ selectedResource }}</strong> yet.
                    </p>
                    <Button
                        v-if="hasPermission('custom-fields.create')"
                        label="Add Field"
                        icon="pi pi-plus"
                        @click="openCreate"
                    />
                </div>
            </template>
        </AdvancedDataTable>
    </AuthenticatedLayout>
</template>
