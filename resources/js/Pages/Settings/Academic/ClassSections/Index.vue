<script setup lang="ts">
/**
 * Settings/Academic/ClassSections/Index.vue
 *
 * Standalone index page for the ClassSection module.
 * Restored from master; Phase 5: removed total-records, global-filter-fields, data-property.
 */

import { computed, h } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useModal } from '@/composables/useModal'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import ClassSectionStatusBadge from '@/Components/ClassSections/ClassSectionStatusBadge.vue'
import { Tag, Button } from 'primevue'
import type { BulkAction, TableAction } from '@/types/datatables'
import type { ClassSection, ClassSectionsPageProps } from '@/types/class-section'
import axios from 'axios'

const props = defineProps<ClassSectionsPageProps>()

const toast = useToast()
const modal = useModal()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

const { enhancedColumns } = useEnhancedColumns<ClassSection>(
    props.columns,
    {
        display_name: {
            render: (row) => h('div', { class: 'flex flex-col' }, [
                h('span', {
                    class: 'font-medium text-gray-900 dark:text-gray-100 text-sm',
                }, row.display_name),
                row.class_level?.school_section
                    ? h('span', {
                        class: 'text-xs text-gray-400 mt-0.5',
                    }, row.class_level.school_section.display_name)
                    : null,
            ]),
        },
        class_level_id: {
            header: 'Class Level',
            render: (row) => row.class_level
                ? h('button', {
                    type: 'button',
                    class: 'text-xs px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-600 ' +
                        'text-gray-600 dark:text-gray-400 hover:border-primary-400 hover:text-primary-600 ' +
                        'dark:hover:text-primary-400 transition-colors',
                    onClick: () => {
                        router.get(route('settings.academic.class-sections.index'), {
                            filters: { class_level_id: { $eq: row.class_level_id } },
                        }, { preserveState: true, replace: true })
                    },
                }, row.class_level.name)
                : h('span', { class: 'text-gray-400 text-xs' }, '—'),
        },
        status: {
            render: (row) => h(ClassSectionStatusBadge, {
                section: row,
                showCapacity: false,
            }),
        },
        capacity: {
            header: 'Capacity',
            render: (row) => h('div', { class: 'flex flex-col' }, [
                h('span', {
                    class: 'text-sm text-gray-700 dark:text-gray-300',
                }, row.is_uncapped ? 'Uncapped' : String(row.capacity)),
                row.students_count !== undefined && !row.is_uncapped
                    ? h('span', {
                        class: 'text-xs text-gray-400',
                    }, `${row.students_count} enrolled`)
                    : null,
            ]),
        },
        students_count: {
            header: 'Students',
            render: (row) => row.students_count !== undefined
                ? h('span', { class: 'text-sm font-medium text-gray-700 dark:text-gray-300' },
                    String(row.students_count))
                : h('span', { class: 'text-gray-400 text-xs' }, '—'),
        },
        form_teacher: {
            header: 'Form Teacher',
            sortable: false,
            filterable: false,
            render: (row) => row.form_teacher
                ? h('span', { class: 'text-sm text-gray-700 dark:text-gray-300' },
                    row.form_teacher.full_name)
                : h('span', { class: 'text-xs text-gray-400 italic' }, 'Not assigned'),
        },
    }
)

const rowActions: TableAction<ClassSection>[] = [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: (row) => !row.is_trashed,
        handler: (row) => {
            modal.open('class-section-form', { section: row })
        },
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (row) => !row.is_trashed,
        confirm: {
            message: (row) => `Delete section "${row.display_name}"? Students must be transferred first.`,
            header: 'Delete Section',
            acceptClass: 'p-button-danger',
        },
        handler: (row) => {
            deleteResource(
                'settings.academic.class-sections',
                [row.id],
                { url: route('settings.academic.class-sections.destroy') }
            )
        },
    },
    {
        label: 'Restore',
        icon: 'pi pi-refresh',
        show: (row) => row.is_trashed,
        handler: (row) => {
            restoreResource(
                'settings.academic.class-sections',
                [row.id],
                { url: route('settings.academic.class-sections.restore') }
            )
        },
    },
]

const bulkActions: BulkAction<ClassSection>[] = [
    {
        label: 'Activate',
        icon: 'pi pi-check-circle',
        severity: 'success',
        action: 'activate',
        handler: async (rows) => {
            await axios.post(route('settings.academic.class-sections.bulk-toggle'), {
                action: 'toggle',
                ids: rows.map(r => r.id),
                is_active: true,
            })
            toast.add({ severity: 'success', summary: 'Activated', detail: `${rows.length} section(s) activated.`, life: 3000 })
            router.reload({ only: ['initialData', 'totalRecords'] })
        },
    },
    {
        label: 'Deactivate',
        icon: 'pi pi-ban',
        severity: 'warn',
        action: 'deactivate',
        handler: async (rows) => {
            await axios.post(route('settings.academic.class-sections.bulk-toggle'), {
                action: 'toggle',
                ids: rows.map(r => r.id),
                is_active: false,
            })
            toast.add({ severity: 'success', summary: 'Deactivated', detail: `${rows.length} section(s) deactivated.`, life: 3000 })
            router.reload({ only: ['initialData', 'totalRecords'] })
        },
    },
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        action: 'delete',
        confirm: {
            message: (rows) => `Delete ${rows.length} selected section(s)?`,
            header: 'Delete Sections',
            acceptClass: 'p-button-danger',
        },
        handler: (rows) => {
            deleteResource(
                'settings.academic.class-sections',
                rows.map(r => r.id),
                { url: route('settings.academic.class-sections.destroy') }
            )
        },
    },
]

const openCreateModal = () => {
    modal.open('class-section-form', { classLevelId: null })
}

const openBulkGenerateModal = () => {
    modal.open('class-section-generate', {
        namingPresets: props.namingPresets,
        availableLevels: [],
    })
}

modal.emitter.value?.on('close', () => {
    router.reload({ only: ['initialData', 'totalRecords'] })
})
</script>

<template>
    <AuthenticatedLayout title="Class Sections" :crumb="[
        { label: 'Settings' },
        { label: 'Academic' },
        { label: 'Class Sections' },
    ]" :buttons="[
        {
            label: 'Bulk Generate',
            icon: 'pi pi-bolt',
            severity: 'secondary',
            outlined: true,
            onClick: openBulkGenerateModal,
        },
        {
            label: 'Add Section',
            icon: 'pi pi-plus',
            onClick: openCreateModal,
        },
    ]" :can-see-trashed="true">
        <AdvancedDataTable :endpoint="route('settings.academic.class-sections.index')" :initial-data="initialData" :columns="enhancedColumns" :actions="rowActions" :bulk-actions="bulkActions" />
    </AuthenticatedLayout>
</template>
