<script setup lang="ts">
/**
 * Settings/Sections/Index.vue — Production-Ready
 *
 * Main list page for SchoolSection management (high-level academic divisions:
 * Pre-Nursery, Nursery, Primary, JSS, SSS, etc.).
 *
 * ── Responsibilities ─────────────────────────────────────────────────────
 * - Renders AdvancedDataTable with server-side pagination, filtering, sorting
 * - Enhances backend column definitions with custom cell renderers
 * - Provides row actions (edit, delete, restore, force-delete) via ActionsDropdown
 * - Provides bulk actions (delete, restore, force-delete, toggle status)
 * - Manages trash view toggle via useTrashedToggle composable
 * - Opens SectionFormModal for create and edit operations
 * - Refreshes table after any mutation via exposed table.refresh()
 *
 * ── Data Flow ────────────────────────────────────────────────────────────
 * 1. Inertia page load → controller passes initialData, columns, totalRecords
 * 2. AdvancedDataTable renders first page immediately (no extra axios call)
 * 3. On sort/filter/paginate → DataTable axios refetch hits index() with wantsJson()
 * 4. Mutations (create/edit/delete) → modal or composable → Inertia redirect
 *    or JSON response → table.refresh() called on success
 *
 * ── Column Strategy ──────────────────────────────────────────────────────
 * Backend (HasTableQuery) generates base column definitions with field names,
 * headers, sortable, filterable, filterType flags. Frontend enhances specific
 * columns with custom render functions via useEnhancedColumns:
 *   - display_name: name + short_code badge side by side
 *   - source: Template / Custom Tag
 *   - is_active: Active / Inactive Tag with color
 *   - class_levels_count / students_count: formatted with icon
 *   - deleted_at: "Deleted on {date}" or dash
 *
 * ── Trash View ───────────────────────────────────────────────────────────
 * useTrashedToggle() injects into AdvancedDataTable's provided API.
 * When showTrashed = true:
 *   - DataTable refetch includes ?trashed=1
 *   - Row actions show Restore + Force Delete only
 *   - Bulk actions show Restore + Force Delete only
 *   - deleted_at column becomes visible
 *
 * ── Permissions ──────────────────────────────────────────────────────────
 * No permission props from controller — usePermissions() reads from
 * page.props.auth.permissions (shared by HandleInertiaRequests middleware).
 *
 * ── Modal Registration ───────────────────────────────────────────────────
 * 'section-form' must be registered in ModalDirectory.ts (file 38).
 * The modal handles both create and edit modes via its `mode` prop,
 * and includes an internal view switch for the templates flow.
 *
 * @see App\Http\Controllers\Settings\SchoolSectionController
 * @see resources/js/Components/Modals/CreateEdit/SectionFormModal.vue
 * @see resources/js/types/school-section.ts
 */

import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { Tag } from 'primevue'
import type { ComponentPublicInstance } from 'vue'
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

// ── Props (from Inertia controller) ───────────────────────────────────────
const props = defineProps<SchoolSectionsPageProps>()

// ── Services ──────────────────────────────────────────────────────────────
const modal = useModal()
const toast = useToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()
const { showTrashed } = useTrashedToggle()

// AdvancedDataTable is a generic component — InstanceType<typeof T> doesn't work
// on generic Vue components in TypeScript. Type the ref with only the exposed API
// surface (defineExpose in AdvancedDataTable exposes refresh and exportData).
const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

const refreshTable = () => tableRef.value?.refresh()

// ──────────────────────────────────────────────────────────────────────────
// Column Definitions
// ──────────────────────────────────────────────────────────────────────────

/**
 * Enhance backend column definitions with custom cell renderers.
 * Base columns come from HasTableQuery (field, header, sortable, filterable).
 * Overrides add render functions for visual cells.
 */
const { enhancedColumns } = useEnhancedColumns<SchoolSection>(
    props.columns,
    {
        // ── Name + short code ──────────────────────────────────────────
        display_name: {
            header: 'Section',
            render: (row) => {
                return {
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
                            class: 'inline-flex items-center px-2 py-0.5 rounded text-xs '
                                + 'font-medium bg-gray-100 text-gray-600 '
                                + 'dark:bg-gray-700 dark:text-gray-300',
                        },
                    ],
                }
            },
        },

        // ── Source badge ───────────────────────────────────────────────
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

        // ── Active status ──────────────────────────────────────────────
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

        // ── Class levels count ─────────────────────────────────────────
        class_levels_count: {
            header: 'Class Levels',
            render: (row) => ({
                template: 'div',
                class: 'flex items-center gap-1.5 text-sm',
                children: [
                    {
                        template: 'i',
                        class: 'pi pi-sitemap text-gray-400',
                    },
                    {
                        template: 'span',
                        text: String(row.class_levels_count ?? 0),
                        class: 'tabular-nums',
                    },
                ],
            }),
        },

        // ── Students count ────────────────────────────────────────────
        students_count: {
            header: 'Students',
            render: (row) => ({
                template: 'div',
                class: 'flex items-center gap-1.5 text-sm',
                children: [
                    {
                        template: 'i',
                        class: 'pi pi-users text-gray-400',
                    },
                    {
                        template: 'span',
                        text: String(row.students_count ?? 0),
                        class: 'tabular-nums',
                    },
                ],
            }),
        },

        // ── Deleted at (visible in trash view) ────────────────────────
        deleted_at: {
            header: 'Deleted',
            sortable: false,
            render: (row) => ({
                template: 'span',
                text: row.deleted_at
                    ? `Deleted ${row.deleted_at}`
                    : '—',
                class: row.deleted_at
                    ? 'text-red-500 dark:text-red-400 text-sm'
                    : 'text-gray-400 text-sm',
            }),
        },
    }
)

// ──────────────────────────────────────────────────────────────────────────
// Row Actions
// ──────────────────────────────────────────────────────────────────────────

const rowActions = computed<TableAction<SchoolSection>[]>(() => [
    // ── Edit (active records only) ─────────────────────────────────────
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: (row) => !isTrashed(row) && hasPermission('sections.update'),
        handler: (row) => openEditModal(row),
    },

    // ── Delete (active records only, no dependents) ───────────────────
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (row) => !isTrashed(row) && hasPermission('sections.delete'),
        confirm: {
            header: 'Delete Section',
            message: (row) => hasDependents(row)
                ? `"${row.display_name}" has class levels or students. `
                + 'Remove them first before deleting this section.'
                : `Delete "${row.display_name}"? It will be moved to trash.`,
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger',
        },
        handler: (row) => {
            if (hasDependents(row)) return // service also blocks this — double safety
            deleteResource(
                'settings.school.sections',
                [row.id],
                { onSuccess: refreshTable }
            )
        },
    },

    // ── Restore (trashed records only) ────────────────────────────────
    {
        label: 'Restore',
        icon: 'pi pi-refresh',
        severity: 'success',
        show: (row) => isTrashed(row) && hasPermission('sections.restore'),
        handler: (row) => {
            restoreResource(
                'settings.school.sections',
                [row.id],
                { onSuccess: refreshTable }
            )
        },
    },

    // ── Force Delete (trashed records only) ───────────────────────────
    {
        label: 'Delete Permanently',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        show: (row) => isTrashed(row) && hasPermission('sections.force-delete'),
        confirm: {
            header: 'Permanently Delete',
            message: (row) =>
                `Permanently delete "${row.display_name}"? `
                + 'This cannot be undone.',
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger',
        },
        handler: (row) => forceDelete([row.id]),
    },
])

// ──────────────────────────────────────────────────────────────────────────
// Bulk Actions
// ──────────────────────────────────────────────────────────────────────────

const bulkActions = computed<BulkAction<SchoolSection>[]>(() => {
    // Trash view — show restore and force-delete only
    if (showTrashed.value) {
        return [
            {
                label: 'Restore Selected',
                icon: 'pi pi-refresh',
                severity: 'success',
                visible: (rows) => rows.length > 0 && hasPermission('sections.restore'),
                handler: (rows) => {
                    restoreResource(
                        'settings.school.sections',
                        rows.map(r => r.id),
                        { onSuccess: refreshTable }
                    )
                },
            },
            {
                label: 'Delete Permanently',
                icon: 'pi pi-times-circle',
                severity: 'danger',
                visible: (rows) => rows.length > 0 && hasPermission('sections.force-delete'),
                confirm: {
                    header: 'Permanently Delete Selected',
                    message: (rows) =>
                        `Permanently delete ${rows.length} section(s)? `
                        + 'This cannot be undone.',
                    icon: 'pi pi-exclamation-triangle',
                    acceptClass: 'p-button-danger',
                },
                handler: (rows) => forceDelete(rows.map(r => r.id)),
            },
        ]
    }

    // Active view — delete and toggle status
    return [
        {
            label: 'Delete Selected',
            icon: 'pi pi-trash',
            severity: 'danger',
            visible: (rows) => rows.length > 0 && hasPermission('sections.delete'),
            confirm: {
                header: 'Delete Selected Sections',
                message: (rows) =>
                    `Delete ${rows.length} section(s)? They will be moved to trash.`,
                icon: 'pi pi-exclamation-triangle',
                acceptClass: 'p-button-danger',
            },
            handler: (rows) => {
                deleteResource(
                    'settings.school.sections',
                    rows.map(r => r.id),
                    { onSuccess: refreshTable }
                )
            },
        },
        {
            label: 'Activate Selected',
            icon: 'pi pi-check-circle',
            severity: 'success',
            visible: (rows) =>
                rows.length > 0
                && hasPermission('sections.update')
                && rows.some(r => !r.is_active),
            handler: (rows) => bulkToggle(rows.map(r => r.id), true),
        },
        {
            label: 'Deactivate Selected',
            icon: 'pi pi-ban',
            severity: 'warning',
            visible: (rows) =>
                rows.length > 0
                && hasPermission('sections.update')
                && rows.some(r => r.is_active),
            handler: (rows) => bulkToggle(rows.map(r => r.id), false),
        },
    ]
})

// ──────────────────────────────────────────────────────────────────────────
// Action Handlers
// ──────────────────────────────────────────────────────────────────────────

/**
 * Open create modal.
 * Modal internal view starts on 'form'. User can switch to 'templates'.
 */
function openCreateModal(): void {
    modal.open('section-form', { mode: 'create', section: null })
        .on('saved', refreshTable)
}

/**
 * Open edit modal with existing section data.
 */
function openEditModal(section: SchoolSection): void {
    modal.open('section-form', { mode: 'edit', section })
        .on('saved', refreshTable)
}

/**
 * Force-delete one or more trashed sections.
 * Uses axios directly — not Inertia redirect — because this is a JSON endpoint.
 */
function forceDelete(ids: string[]): void {
    import('axios').then(({ default: axios }) => {
        axios
            .delete(route('settings.school.sections.force-delete'), { data: { ids } })
            .then(() => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `${ids.length} section(s) permanently deleted.`,
                    life: 4000,
                })
                refreshTable()
            })
            .catch((err) => {
                const message = err.response?.data?.message
                    ?? 'Failed to permanently delete sections.'
                toast.add({
                    severity: 'error',
                    summary: 'Delete Failed',
                    detail: message,
                    life: 6000,
                })
            })
    })
}

/**
 * Bulk activate or deactivate sections.
 */
function bulkToggle(ids: string[], isActive: boolean): void {
    import('axios').then(({ default: axios }) => {
        axios
            .post(route('settings.school.sections.bulk-toggle'), {
                action: 'toggle',
                ids,
                is_active: isActive,
            })
            .then(() => {
                const state = isActive ? 'activated' : 'deactivated'
                toast.add({
                    severity: 'success',
                    summary: 'Updated',
                    detail: `${ids.length} section(s) ${state}.`,
                    life: 4000,
                })
                refreshTable()
            })
            .catch((err) => {
                const message = err.response?.data?.message
                    ?? 'Failed to update section status.'
                toast.add({
                    severity: 'error',
                    summary: 'Update Failed',
                    detail: message,
                    life: 6000,
                })
            })
    })
}

// ──────────────────────────────────────────────────────────────────────────
// Page Header Config
// ──────────────────────────────────────────────────────────────────────────

const pageButtons = computed(() => {
    if (!hasPermission('sections.create')) return []

    return [
        {
            label: 'Add Section',
            icon: 'pi pi-plus',
            onClick: openCreateModal,
        },
    ]
})
</script>

<template>
    <AuthenticatedLayout title="School Sections" :crumb="[
        { label: 'Settings' },
        { label: 'School' },
        { label: 'Sections' },
    ]" :buttons="pageButtons" :can-see-trashed="hasPermission('sections.delete')">
        <AdvancedDataTable ref="tableRef" :endpoint="route('settings.school.sections.index')"
            :initial-data="props.initialData" :total-records="props.totalRecords" :columns="enhancedColumns"
            :global-filter-fields="props.globalFilterables" :actions="rowActions" :bulk-actions="bulkActions"
            data-property="data" />
    </AuthenticatedLayout>
</template>
