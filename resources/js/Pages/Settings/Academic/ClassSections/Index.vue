<script setup lang="ts">
/**
 * Settings/Academic/ClassSections/Index.vue
 *
 * Standalone index page for the ClassSection module.
 *
 * ── Page Overview ─────────────────────────────────────────────────────────────
 * A single page that shows ALL class sections across the school, filterable
 * by class level, status, school section, and via global search.
 *
 * Visiting from a ClassLevel row automatically pre-applies a Purity filter:
 *   ?filters[class_level_id][$eq]=uuid
 * The DataTable renders with that filter active. The user can clear it to
 * see all sections, or change it to a different level — without leaving the page.
 *
 * ── Layout ────────────────────────────────────────────────────────────────────
 * AuthenticatedLayout → PageHeader (title + breadcrumb + header buttons)
 *                     → AdvancedDataTable (with column defs from Inertia props)
 *
 * ── Header Buttons ────────────────────────────────────────────────────────────
 * [Bulk Generate]   → opens BulkGenerateSectionsModal (ModalDirectory)
 * [Add Section]     → opens ClassSectionFormModal (ModalDirectory)
 *
 * ── Columns ───────────────────────────────────────────────────────────────────
 * display_name  → section name (primary identifier)
 * class_level   → parent class level name (linked to filter)
 * status        → Active/Inactive badge (ClassSectionStatusBadge)
 * capacity      → "40" or "Uncapped", with enrollment count if available
 * students_count → enrolled students
 * form_teacher   → form teacher name
 * room           → physical room (hidden by default)
 * sort_order     → display order (hidden by default)
 *
 * ── Bulk Actions ─────────────────────────────────────────────────────────────
 * Delete selected / Restore selected / Activate / Deactivate
 *
 * ── Row Actions ──────────────────────────────────────────────────────────────
 * Edit → opens ClassSectionFormModal
 * Delete → confirm + soft-delete
 * Restore (trashed rows) → restore
 *
 * ── Trashed Toggle ───────────────────────────────────────────────────────────
 * PageHeader uses useTrashedToggle() (injected from AdvancedDataTable)
 * to toggle ?trashed=1 and switch the DataTable to show archived sections.
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

// ── Page props ────────────────────────────────────────────────────────────────
const props = defineProps<ClassSectionsPageProps>()

const toast = useToast()
const modal = useModal()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

// ── Enhanced columns ──────────────────────────────────────────────────────────
const { enhancedColumns } = useEnhancedColumns<ClassSection>(
    props.columns,
    {
        // Display name — primary cell with school section context
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

        // Class level — clickable chip that applies a filter
        class_level_id: {
            header: 'Class Level',
            render: (row) => row.class_level
                ? h('button', {
                    type: 'button',
                    class: 'text-xs px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-600 ' +
                        'text-gray-600 dark:text-gray-400 hover:border-primary-400 hover:text-primary-600 ' +
                        'dark:hover:text-primary-400 transition-colors',
                    onClick: () => {
                        // Navigate to filtered view for this level
                        router.get(route('settings.academic.class-sections.index'), {
                            filters: { class_level_id: { $eq: row.class_level_id } },
                        }, { preserveState: true, replace: true })
                    },
                }, row.class_level.name)
                : h('span', { class: 'text-gray-400 text-xs' }, '—'),
        },

        // Status badge
        status: {
            render: (row) => h(ClassSectionStatusBadge, {
                section: row,
                showCapacity: false,
            }),
        },

        // Capacity column
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

        // Students count
        students_count: {
            header: 'Students',
            render: (row) => row.students_count !== undefined
                ? h('span', { class: 'text-sm font-medium text-gray-700 dark:text-gray-300' },
                    String(row.students_count))
                : h('span', { class: 'text-gray-400 text-xs' }, '—'),
        },

        // Form teacher
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

// ── Row actions ───────────────────────────────────────────────────────────────
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

// ── Bulk actions ──────────────────────────────────────────────────────────────
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

// ── Header button handlers ────────────────────────────────────────────────────
const openCreateModal = () => {
    modal.open('class-section-form', { classLevelId: null })
}

const openBulkGenerateModal = () => {
    modal.open('class-section-generate', {
        namingPresets: props.namingPresets,
        // Available levels are fetched inside the modal via async call
        // to avoid bloating the Inertia page props
        availableLevels: [],
    })
}

// Refresh when modal saves
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
        <AdvancedDataTable :endpoint="route('settings.academic.class-sections.index')" :initial-data="initialData"
            :total-records="totalRecords" :columns="enhancedColumns" :actions="rowActions" :bulk-actions="bulkActions"
            :global-filter-fields="['display_name', 'name', 'room']" data-property="initialData" />
    </AuthenticatedLayout>
</template>
