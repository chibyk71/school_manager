<script setup lang="ts">
/**
 * Settings/Sections/Index.vue — Production-Ready
 * Restored from master; Phase 5: removed total-records, global-filter-fields, data-property.
 *
 * Main list page for SchoolSection management.
 */

import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { Tag } from 'primevue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { useModal } from '@/composables/useModal'
import { usePermissions } from '@/composables/usePermissions'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { useTrashedToggle } from '@/composables/useTrashedToggle'
import { isTrashed, hasDependents } from '@/types/school-section'
import type { SchoolSection, SchoolSectionsPageProps } from '@/types/school-section'
import type { BulkAction, TableAction } from '@/types/datatables'

const props = defineProps<SchoolSectionsPageProps>()

const initialTableResponse = computed(() => {
    const data = props.data ?? props.initialData ?? []
    const columns = props.columns ?? []
    if (!columns.length) return null
    if (props.meta) {
        return { data, columns: columns as any, meta: props.meta }
    }
    return null
})

const modal = useModal()
const toast = useToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()
const { showTrashed } = useTrashedToggle()

const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)
const refreshTable = () => tableRef.value?.refresh()

const { enhancedColumns } = useEnhancedColumns<SchoolSection>(
    props.columns,
    {
        display_name: {
            header: 'Section',
            render: (row) => ({
                template: 'div',
                class: 'flex items-center gap-2',
                children: [
                    {
                        template: 'span',
                        text: row.display_name,
                        class: 'font-medium text-gray-900 dark:text-gray-100',
                    },
                    {
                        template: 'span',
                        text: row.short_code,
                        class: 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                    },
                ],
            }),
        },
        source: {
            header: 'Type',
            sortable: false,
            render: (row) => ({
                component: Tag as any,
                props: {
                    value: row.source === 'template' ? 'Template' : 'Custom',
                    severity: row.source === 'template' ? 'info' : 'secondary',
                    rounded: true,
                },
            }),
        },
        is_active: {
            header: 'Status',
            render: (row) => ({
                component: Tag as any,
                props: {
                    value: row.is_active ? 'Active' : 'Inactive',
                    severity: row.is_active ? 'success' : 'danger',
                    rounded: true,
                },
            }),
        },
        class_levels_count: {
            header: 'Class Levels',
            render: (row) => ({
                template: 'div',
                class: 'flex items-center gap-1.5 text-sm',
                children: [
                    { template: 'i', class: 'pi pi-sitemap text-gray-400' },
                    { template: 'span', text: String(row.class_levels_count ?? 0), class: 'tabular-nums' },
                ],
            }),
        },
        students_count: {
            header: 'Students',
            render: (row) => ({
                template: 'div',
                class: 'flex items-center gap-1.5 text-sm',
                children: [
                    { template: 'i', class: 'pi pi-users text-gray-400' },
                    { template: 'span', text: String(row.students_count ?? 0), class: 'tabular-nums' },
                ],
            }),
        },
        deleted_at: {
            header: 'Deleted',
            sortable: false,
            render: (row) => ({
                template: 'span',
                text: row.deleted_at ? `Deleted ${row.deleted_at}` : '—',
                class: row.deleted_at ? 'text-red-500 dark:text-red-400 text-sm' : 'text-gray-400 text-sm',
            }),
        },
    }
)

const rowActions = computed<TableAction<SchoolSection>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: (row) => !isTrashed(row) && hasPermission('sections.update'),
        handler: (row) => openEditModal(row),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (row) => !isTrashed(row) && hasPermission('sections.delete'),
        confirm: {
            header: 'Delete Section',
            message: (row) => hasDependents(row)
                ? `"${row.display_name}" has class levels or students. Remove them first before deleting this section.`
                : `Delete "${row.display_name}"? It will be moved to trash.`,
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger',
        },
        handler: (row) => {
            if (hasDependents(row)) return
            deleteResource('settings.school.sections', [row.id], { onSuccess: refreshTable })
        },
    },
    {
        label: 'Restore',
        icon: 'pi pi-refresh',
        severity: 'success',
        show: (row) => isTrashed(row) && hasPermission('sections.restore'),
        handler: (row) => {
            restoreResource('settings.school.sections', [row.id], { onSuccess: refreshTable })
        },
    },
    {
        label: 'Delete Permanently',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        show: (row) => isTrashed(row) && hasPermission('sections.force-delete'),
        confirm: {
            header: 'Permanently Delete',
            message: (row) => `Permanently delete "${row.display_name}"? This cannot be undone.`,
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger',
        },
        handler: (row) => forceDelete([row.id]),
    },
])

const bulkActions = computed<BulkAction<SchoolSection>[]>(() => {
    if (showTrashed.value) {
        return [
            {
                label: 'Restore Selected',
                icon: 'pi pi-refresh',
                severity: 'success',
                visible: (rows) => rows.length > 0 && hasPermission('sections.restore'),
                handler: (rows) => {
                    restoreResource('settings.school.sections', rows.map(r => r.id), { onSuccess: refreshTable })
                },
            },
            {
                label: 'Delete Permanently',
                icon: 'pi pi-times-circle',
                severity: 'danger',
                visible: (rows) => rows.length > 0 && hasPermission('sections.force-delete'),
                confirm: {
                    header: 'Permanently Delete Selected',
                    message: (rows) => `Permanently delete ${rows.length} section(s)? This cannot be undone.`,
                    icon: 'pi pi-exclamation-triangle',
                    acceptClass: 'p-button-danger',
                },
                handler: (rows) => forceDelete(rows.map(r => r.id)),
            },
        ]
    }
    return [
        {
            label: 'Delete Selected',
            icon: 'pi pi-trash',
            severity: 'danger',
            visible: (rows) => rows.length > 0 && hasPermission('sections.delete'),
            confirm: {
                header: 'Delete Selected Sections',
                message: (rows) => `Delete ${rows.length} section(s)? They will be moved to trash.`,
                icon: 'pi pi-exclamation-triangle',
                acceptClass: 'p-button-danger',
            },
            handler: (rows) => {
                deleteResource('settings.school.sections', rows.map(r => r.id), { onSuccess: refreshTable })
            },
        },
        {
            label: 'Activate Selected',
            icon: 'pi pi-check-circle',
            severity: 'success',
            visible: (rows) => rows.length > 0 && hasPermission('sections.update') && rows.some(r => !r.is_active),
            handler: (rows) => bulkToggle(rows.map(r => r.id), true),
        },
        {
            label: 'Deactivate Selected',
            icon: 'pi pi-ban',
            severity: 'warning',
            visible: (rows) => rows.length > 0 && hasPermission('sections.update') && rows.some(r => r.is_active),
            handler: (rows) => bulkToggle(rows.map(r => r.id), false),
        },
    ]
})

function openCreateModal(): void {
    modal.open('section-form', { mode: 'create', section: null }).on('saved', refreshTable)
}

function openEditModal(section: SchoolSection): void {
    modal.open('section-form', { mode: 'edit', section }).on('saved', refreshTable)
}

function forceDelete(ids: string[]): void {
    import('axios').then(({ default: axios }) => {
        axios
            .delete(route('settings.school.sections.force-delete'), { data: { ids } })
            .then(() => {
                toast.add({ severity: 'success', summary: 'Deleted', detail: `${ids.length} section(s) permanently deleted.`, life: 4000 })
                refreshTable()
            })
            .catch((err) => {
                const message = err.response?.data?.message ?? 'Failed to permanently delete sections.'
                toast.add({ severity: 'error', summary: 'Delete Failed', detail: message, life: 6000 })
            })
    })
}

function bulkToggle(ids: string[], isActive: boolean): void {
    import('axios').then(({ default: axios }) => {
        axios
            .post(route('settings.school.sections.bulk-toggle'), { ids, is_active: isActive })
            .then(() => {
                toast.add({
                    severity: 'success',
                    summary: isActive ? 'Activated' : 'Deactivated',
                    detail: `${ids.length} section(s) updated.`,
                    life: 4000,
                })
                refreshTable()
            })
            .catch(() => {
                toast.add({ severity: 'error', summary: 'Update Failed', detail: 'Could not update sections.', life: 5000 })
            })
    })
}
</script>

<template>
    <AuthenticatedLayout
        title="School Sections"
        :crumb="[{ label: 'Settings' }, { label: 'School Sections' }]"
        :buttons="[
            {
                label: 'Add Section',
                icon: 'pi pi-plus',
                onClick: openCreateModal,
                class: { hidden: !hasPermission('sections.create') },
            },
        ]"
        :can-see-trashed="true"
    >
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('settings.school.sections.index')"
            :initial-response="initialTableResponse"
            :columns="enhancedColumns"
            :actions="rowActions"
            :bulk-actions="bulkActions"
        />
    </AuthenticatedLayout>
</template>
