<script setup lang="ts">
import { computed, h } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import ClassSectionStatusBadge from '@/Components/ClassSections/ClassSectionStatusBadge.vue'
import { Tag, Button } from 'primevue'
import type { BulkAction, TableAction } from '@/types/datatables'
import type { ClassSection, ClassSectionsPageProps } from '@/types/class-section'
import axios from 'axios'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { modals } from '@/helpers'

const props = defineProps<ClassSectionsPageProps>()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

const initialData = computed(() => props.initialData ?? [])
const enhancedColumns = computed(() => props.columns ?? [])

const openCreateModal = () => {
    modals.open('class-section-form', { mode: 'create' })
}

const rowActions = computed<TableAction<ClassSection>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => modals.open('class-section-form', { mode: 'edit', section: row }),
        show: () => hasPermission('class-sections.update'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('settings.academic.class-sections.destroy', [row.id]),
        show: () => hasPermission('class-sections.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'settings.academic.class-sections.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('class-sections.delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout
        title="Class Sections"
        :crumb="[{ label: 'Settings' }, { label: 'Academic' }, { label: 'Class Sections' }]"
        :buttons="[
            {
                label: 'Add Section',
                icon: 'pi pi-plus',
                onClick: openCreateModal,
            },
        ]"
        :can-see-trashed="true"
    >
        <AdvancedDataTable
            :endpoint="route('settings.academic.class-sections.index')"
            :initial-data="initialData"
            :columns="enhancedColumns"
            :actions="rowActions"
            :bulk-actions="bulkActions"
        />
    </AuthenticatedLayout>
</template>
