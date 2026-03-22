<script setup lang="ts">
/**
 * Pages/Sections/Partials/ClassLevelsTab.vue
 *
 * Class levels management tab — rendered inside Show.vue when the
 * "Class Levels" tab is active.
 *
 * Features implemented:
 * ─────────────────────────────────────────────────────────────────────────────
 * - AdvancedDataTable showing all class levels for the current section
 * - Empty state with prominent "Generate from Preset" CTA when no levels exist
 * - "Add Level" button opens FormModal (single create)
 * - "Generate from Preset" button (smaller, always visible) opens BulkGenerateModal
 * - Row actions: Edit, Toggle Active, Delete (with confirmation)
 * - Bulk actions: Delete selected
 * - Trash toggle (show/hide soft-deleted levels)
 * - Restore action available when trash toggle is on
 * - All table columns defined here — sequence, name, display_name, alias,
 *   max_arms, is_active badge, actions
 * - Sequence badge styled by position (first = green, last = gray, others = blue)
 * - is_active renders as a colored badge not a raw boolean
 * - Refreshes table after any modal success event
 *
 * Props:
 * ─────────────────────────────────────────────────────────────────────────────
 * - section: the parent SchoolSection (passed down from Show.vue)
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Rendered by Show.vue inside the class-levels tab panel
 * - Opens FormModal and BulkGenerateModal from ModalDirectory
 * - DataTable endpoint: GET /sections/{section}/class-levels
 * - All mutations go through the modal forms → Inertia/Axios → controller
 */

import { computed, ref, h } from 'vue'
import { useModal } from '@/composables/useModal'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { usePermissions } from '@/composables/usePermissions'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { Badge, Button } from 'primevue'
import type { ColumnDefinition, TableAction, BulkAction } from '@/types/datatables'

// ── Types ─────────────────────────────────────────────────────────────────────

interface SchoolSection {
    id: string
    name: string
    description: string | null
    is_active: boolean
    school: { id: string; name: string }
}

interface ClassLevel {
    id: string
    name: string
    display_name: string | null
    alias: string | null
    description: string | null
    sequence: number
    max_arms: number | null
    is_active: boolean
    is_deletable: boolean
    class_sections_count: number
    short_label: string
    full_label: string
    deleted_at: string | null
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    section: SchoolSection
}>()

// ── Composables ───────────────────────────────────────────────────────────────

const modal = useModal()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()
const { hasPermission } = usePermissions()

// ── DataTable ref (for manual refresh) ───────────────────────────────────────

const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

const refresh = () => tableRef.value?.refresh()

// ── Endpoint ──────────────────────────────────────────────────────────────────

const endpoint = computed(() =>
    route('class-levels.index', props.section.id)
)

// ── Column definitions ────────────────────────────────────────────────────────

const columns = computed<ColumnDefinition<ClassLevel>[]>(() => [
    {
        field: 'sequence',
        header: 'Seq',
        sortable: true,
        filterable: false,
        width: '70px',
        bodyClass: 'text-center',
        headerClass: 'text-center',
        render: (row) => h(Badge, {
            value: String(row.sequence),
            severity: 'secondary',
            class: 'text-xs font-mono',
        }),
    },
    {
        field: 'name',
        header: 'Name',
        sortable: true,
        filterable: true,
        filterType: 'text',
        render: (row) => h('div', { class: 'flex flex-col gap-0.5' }, [
            h('span', { class: 'font-medium text-gray-900 dark:text-white' }, row.name),
            row.alias
                ? h('span', { class: 'text-xs text-gray-400 dark:text-gray-500 font-mono' }, row.alias)
                : null,
        ]),
    },
    {
        field: 'display_name',
        header: 'Display Name',
        sortable: true,
        filterable: true,
        filterType: 'text',
        hidden: true, // togglable — hidden by default, user can show it
        formatter: (value) => value ?? '—',
    },
    {
        field: 'max_arms',
        header: 'Max Arms',
        sortable: true,
        filterable: false,
        width: '110px',
        bodyClass: 'text-center',
        headerClass: 'text-center',
        render: (row) => h('span', {
            class: row.max_arms
                ? 'text-gray-700 dark:text-gray-300'
                : 'text-gray-400 dark:text-gray-600 italic text-xs',
        }, row.max_arms ? String(row.max_arms) : 'Unlimited'),
    },
    {
        field: 'class_sections_count',
        header: 'Streams',
        sortable: true,
        filterable: false,
        width: '90px',
        bodyClass: 'text-center',
        headerClass: 'text-center',
        formatter: (value) => String(value ?? 0),
    },
    {
        field: 'is_active',
        header: 'Status',
        sortable: true,
        filterable: true,
        filterType: 'boolean',
        width: '100px',
        bodyClass: 'text-center',
        headerClass: 'text-center',
        render: (row) => h(Badge, {
            value: row.is_active ? 'Active' : 'Inactive',
            severity: row.is_active ? 'success' : 'secondary',
        }),
    },
])

// ── Row actions ───────────────────────────────────────────────────────────────

const actions = computed<TableAction<ClassLevel>[]>(() => {
    const list: TableAction<ClassLevel>[] = []

    // Edit — always visible
    if (hasPermission('class-levels.update')) {
        list.push({
            label: 'Edit',
            icon: 'pi pi-pencil',
            handler: (row) => openFormModal(row),
        })
    }

    // Toggle active/inactive
    if (hasPermission('class-levels.update')) {
        list.push({
            label: (row) => row.is_active ? 'Deactivate' : 'Activate',
            icon: (row) => row.is_active ? 'pi pi-ban' : 'pi pi-check-circle',
            show: (row) => !row.deleted_at,
            handler: (row) => toggleActive(row),
            confirm: {
                message: (row) => row.is_active
                    ? `Deactivate "${row.name}"? It will no longer appear in active dropdowns.`
                    : `Activate "${row.name}"?`,
                header: 'Confirm Status Change',
            },
        })
    }

    // Delete (soft) — only if deletable and not already trashed
    if (hasPermission('class-levels.delete')) {
        list.push({
            label: 'Delete',
            icon: 'pi pi-trash',
            severity: 'danger',
            show: (row) => !row.deleted_at,
            disabled: (row) => !row.is_deletable,
            handler: (row) => deleteResource(
                'class-levels',
                [row.id],
                {
                    url: route('class-levels.destroy', props.section.id),
                    onSuccess: refresh,
                }
            ),
            confirm: {
                message: (row) => `Delete "${row.name}"? This can be undone from the trash.`,
                header: 'Delete Class Level',
                acceptClass: 'p-button-danger',
            },
        })
    }

    // Restore — only visible when trash toggle is on
    if (hasPermission('class-levels.restore')) {
        list.push({
            label: 'Restore',
            icon: 'pi pi-undo',
            severity: 'success',
            show: (row) => !!row.deleted_at,
            handler: (row) => restoreResource(
                'class-levels',
                [row.id],
                {
                    url: route('class-levels.restore', props.section.id),
                    onSuccess: refresh,
                }
            ),
        })
    }

    // Force delete — only visible when trashed and user has permission
    if (hasPermission('class-levels.force-delete')) {
        list.push({
            label: 'Delete Permanently',
            icon: 'pi pi-times-circle',
            severity: 'danger',
            show: (row) => !!row.deleted_at,
            handler: (row) => deleteResource(
                'class-levels',
                [row.id],
                {
                    url: route('class-levels.force-delete', props.section.id),
                    onSuccess: refresh,
                }
            ),
            confirm: {
                message: (row) => `Permanently delete "${row.name}"? This cannot be undone.`,
                header: 'Permanent Delete',
                acceptClass: 'p-button-danger',
            },
        })
    }

    return list
})

// ── Bulk actions ──────────────────────────────────────────────────────────────

const bulkActions = computed<BulkAction<ClassLevel>[]>(() => {
    const list: BulkAction<ClassLevel>[] = []

    if (hasPermission('class-levels.delete')) {
        list.push({
            label: 'Delete Selected',
            icon: 'pi pi-trash',
            severity: 'danger',
            handler: (rows) => deleteResource(
                'class-levels',
                rows.map(r => r.id),
                {
                    url: route('class-levels.destroy', props.section.id),
                    onSuccess: refresh,
                }
            ),
            confirm: {
                message: (rows) => `Delete ${rows.length} class level(s)? This can be undone from the trash.`,
                header: 'Delete Class Levels',
                acceptClass: 'p-button-danger',
            },
        })
    }

    return list
})

// ── Modal helpers ─────────────────────────────────────────────────────────────

/**
 * Open the create/edit modal.
 * Passing no classLevel = create mode.
 * Passing a classLevel = edit mode.
 */
const openFormModal = (classLevel?: ClassLevel) => {
    const instance = modal.open('class-level-form', {
        section: props.section,
        classLevel: classLevel ?? null,
    })

    instance.on('saved', refresh)
}

/**
 * Open the bulk generate (preset picker) modal.
 */
const openBulkGenerateModal = () => {
    const instance = modal.open('class-level-bulk-generate', {
        section: props.section,
    })

    instance.on('generated', refresh)
}

// ── Toggle active ─────────────────────────────────────────────────────────────

/**
 * Toggle the is_active status of a class level via a PATCH request.
 * Uses Axios directly since this is a targeted single-field update,
 * not a full form submission.
 */
const toggleActive = async (row: ClassLevel) => {
    try {
        await (await import('axios')).default.patch(
            route('class-levels.update', {
                section: props.section.id,
                classLevel: row.id,
            }),
            { is_active: !row.is_active }
        )
        refresh()
    } catch (e: any) {
        const { useToast } = await import('primevue/usetoast')
        useToast().add({
            severity: 'error',
            summary: 'Update failed',
            detail: e.response?.data?.message ?? 'Could not update status.',
            life: 5000,
        })
    }
}
</script>

<template>
    <div>
        <!-- ── Header row: title + action buttons ────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Class Levels
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Manage the academic levels within this section.
                    Sequence order drives the student promotion path.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <!-- Bulk generate — smaller button, always visible -->
                <Button v-if="hasPermission('class-levels.create')" label="Generate from Preset" icon="pi pi-magic-wand"
                    severity="secondary" outlined size="small" @click="openBulkGenerateModal" />

                <!-- Add single level -->
                <Button v-if="hasPermission('class-levels.create')" label="Add Level" icon="pi pi-plus" size="small"
                    @click="openFormModal()" />
            </div>
        </div>

        <!-- ── DataTable ──────────────────────────────────────────────────── -->
        <AdvancedDataTable ref="tableRef" :endpoint="endpoint" :columns="columns" :actions="actions"
            :bulk-actions="bulkActions" :initial-params="{ section_id: section.id }" data-property="data">
            <!-- Empty state — prominent CTA when no levels exist -->
            <template #empty>
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                        <i class="pi pi-list text-2xl text-primary" aria-hidden="true" />
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        No class levels yet
                    </h3>

                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mb-6">
                        This section has no class levels defined.
                        Use a preset to quickly generate common structures,
                        or add levels one at a time.
                    </p>

                    <div v-if="hasPermission('class-levels.create')" class="flex flex-col sm:flex-row gap-3">
                        <Button label="Generate from Preset" icon="pi pi-magic-wand" @click="openBulkGenerateModal" />
                        <Button label="Add Manually" icon="pi pi-plus" severity="secondary" outlined
                            @click="openFormModal()" />
                    </div>
                </div>
            </template>
        </AdvancedDataTable>
    </div>
</template>
