<script setup lang="ts">
import { computed, ref } from 'vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { Button } from 'primevue'
import type { BulkAction, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import { modals } from '@/helpers'

const props = defineProps<{
    section: { id: string | number; name?: string }
    columns: any[]
    endpoint: string
}>()

const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref()

const columns = computed(() => props.columns ?? [])
const endpoint = computed(() => props.endpoint)

const openFormModal = (level?: any) => {
    modals.open('class-level-form', { section: props.section, level })
}

const openBulkGenerateModal = () => {
    modals.open('class-level-bulk-generate', { section: props.section })
}

const actions = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => openFormModal(row),
        show: () => hasPermission('class-levels.update'),
    },
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
    <div>
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="endpoint"
            :columns="columns"
            :actions="actions"
            :bulk-actions="bulkActions"
            :initial-params="{ section_id: section.id }"
        >
            <template #empty>
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                        <i class="pi pi-list text-2xl text-primary" aria-hidden="true" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        No class levels yet
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mb-6">
                        This section has no class levels defined. Use a preset to quickly generate common
                        structures, or add levels one at a time.
                    </p>
                    <div v-if="hasPermission('class-levels.create')" class="flex flex-col sm:flex-row gap-3">
                        <Button label="Generate from Preset" icon="pi pi-magic-wand" @click="openBulkGenerateModal" />
                        <Button
                            label="Add Manually"
                            icon="pi pi-plus"
                            severity="secondary"
                            outlined
                            @click="openFormModal()"
                        />
                    </div>
                </div>
            </template>
        </AdvancedDataTable>
    </div>
</template>
