<script setup lang="ts">
/**
 * Pages/Settings/Academic/ClassLevels.vue
 *
 * Global class levels management page under Settings → Academic.
 *
 * Purpose:
 * ─────────────────────────────────────────────────────────────────────────────
 * Provides a school-wide overview of ALL class levels across ALL sections.
 * Useful for super-admins auditing the full level structure, performing
 * bulk operations across sections, or quickly navigating to a specific
 * section's levels.
 *
 * This is NOT the primary management surface — that is the section detail
 * page (Show.vue → ClassLevelsTab.vue). This page is the bird's-eye view.
 *
 * Features implemented:
 * ─────────────────────────────────────────────────────────────────────────────
 * - AdvancedDataTable showing all levels across all sections
 * - Section filter dropdown — filters the table to a specific section
 * - Section name column visible (unlike the section tab where it's hidden)
 * - "Go to section" quick link on each row navigates to that section's tab
 * - Same row actions as ClassLevelsTab (edit, toggle active, delete, restore,
 *   force delete) — modals are reused, route params adapt per row
 * - Trash toggle inherited from AdvancedDataTable
 * - Stats summary bar: total levels, active, inactive, across how many sections
 * - Inertia page — section list and initial stats come as page props,
 *   table data is fetched via Axios (DataTable JSON endpoint)
 * - Settings sidebar navigation via useSettingsNavigation composable
 *
 * Props (from ClassLevelController::globalIndex via Inertia):
 * ─────────────────────────────────────────────────────────────────────────────
 * - classLevels: paginated DataTable result (initial SSR data)
 * - sections:    list of sections for the filter dropdown
 * - filters:     active filters from the URL (section_id, trashed)
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Route: GET /settings/academic/class-levels → settings.academic.class-levels.index
 * - Linked from Settings sidebar under "Academic Settings"
 * - Uses the same FormModal and BulkGenerateModal as ClassLevelsTab
 * - All mutations route to the correct section-scoped endpoints via row data
 */

import { computed, ref, h } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import { useModal } from '@/composables/useModal'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import { usePermissions } from '@/composables/usePermissions'
import { Badge, Button, Select } from 'primevue'
import type { ColumnDefinition, TableAction, BulkAction } from '@/types/datatables'
import SettingsLayout from '../Partials/SettingsLayout.vue'
import SettingsSidebar from '../Partials/SettingsSidebar.vue'

// ── Types ─────────────────────────────────────────────────────────────────────

interface Section {
    id: string
    name: string
}

interface ClassLevel {
    id: string
    name: string
    display_name: string | null
    alias: string | null
    sequence: number
    max_arms: number | null
    is_active: boolean
    is_deletable: boolean
    class_sections_count: number
    deleted_at: string | null
    section: {
        id: string
        name: string
    } | null
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    classLevels: {
        data: ClassLevel[]
        totalRecords: number
    }
    sections: Section[]
    filters: {
        section_id?: string | null
        trashed?: boolean
    }
}>()

// ── Composables ───────────────────────────────────────────────────────────────

const { academicSettingsNav } = useSettingsNavigation()
const modal = useModal()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()
const { hasPermission } = usePermissions()

// ── Table ref ─────────────────────────────────────────────────────────────────
const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

const refresh = () => tableRef.value?.refresh()

// ── Section filter ────────────────────────────────────────────────────────────

/**
 * Bound to the section filter Select.
 * When changed, updates the URL via Inertia so the filter persists on refresh
 * AND passes the new section_id to the DataTable as initialParams.
 */
const selectedSectionId = ref<string | null>(props.filters.section_id ?? null)

const applyFilter = () => {
    router.get(
        route('settings.academic.class-levels.index'),
        { section_id: selectedSectionId.value ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}

// ── DataTable endpoint ────────────────────────────────────────────────────────

/**
 * Global endpoint — no section in the path.
 * Section filter is passed as a query param via initialParams.
 */
const endpoint = route('settings.academic.class-levels.index')

const initialParams = computed(() => ({
    ...(selectedSectionId.value ? { section_id: selectedSectionId.value } : {}),
}))

// ── Stats ─────────────────────────────────────────────────────────────────────

/**
 * Summary stats computed from the initial page props.
 * These are approximate (based on the first page load) — a future iteration
 * can add a dedicated stats endpoint if real-time accuracy is needed.
 */
const stats = computed(() => {
    const levels = props.classLevels.data
    return {
        total: props.classLevels.totalRecords,
        active: levels.filter(l => l.is_active && !l.deleted_at).length,
        inactive: levels.filter(l => !l.is_active && !l.deleted_at).length,
        sections: new Set(levels.map(l => l.section?.id).filter(Boolean)).size,
    }
})

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
        render: (row) => h('span', {
            class: 'font-mono text-sm font-semibold text-gray-600 dark:text-gray-400',
        }, String(row.sequence)),
    },
    {
        field: 'name',
        header: 'Name',
        sortable: true,
        filterable: true,
        filterType: 'text',
        render: (row) => h('div', { class: 'flex flex-col gap-0.5' }, [
            h('span', {
                class: 'font-medium text-gray-900 dark:text-white',
            }, row.name),
            row.alias
                ? h('span', {
                    class: 'text-xs text-gray-400 dark:text-gray-500 font-mono',
                }, row.alias)
                : null,
        ]),
    },
    {
        field: 'section',
        header: 'Section',
        sortable: false,
        filterable: false,
        render: (row) => row.section
            ? h('div', { class: 'flex items-center gap-2' }, [
                h('span', {
                    class: 'text-sm text-gray-700 dark:text-gray-300',
                }, row.section.name),
                // Quick link to the section's class levels tab
                h('a', {
                    href: route('sections.show', {
                        section: row.section.id,
                        tab: 'class-levels',
                    }),
                    class: 'text-primary hover:text-primary/70 transition-colors',
                    title: `Go to ${row.section.name}`,
                    onClick: (e: MouseEvent) => {
                        e.preventDefault()
                        router.visit(route('sections.show', {
                            section: row?.section?.id,
                            tab: 'class-levels',
                        }))
                    },
                }, [
                    h('i', { class: 'pi pi-arrow-up-right text-xs' }),
                ]),
            ])
            : h('span', { class: 'text-gray-400' }, '—'),
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

// ── Route helpers (section-scoped) ────────────────────────────────────────────

/**
 * All mutation routes are section-scoped.
 * We derive the section from the row data.
 */
const sectionId = (row: ClassLevel) => row.section?.id ?? ''

const destroyRoute = (row: ClassLevel) => route('class-levels.destroy', sectionId(row))
const restoreRoute = (row: ClassLevel) => route('class-levels.restore', sectionId(row))
const forceDelRoute = (row: ClassLevel) => route('class-levels.force-delete', sectionId(row))
const updateRoute = (row: ClassLevel) => route('class-levels.update', {
    section: sectionId(row),
    classLevel: row.id,
})

// ── Toggle active ─────────────────────────────────────────────────────────────

const toggleActive = async (row: ClassLevel) => {
    try {
        await (await import('axios')).default.patch(
            updateRoute(row),
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

// ── Row actions ───────────────────────────────────────────────────────────────

const actions = computed<TableAction<ClassLevel>[]>(() => {
    const list: TableAction<ClassLevel>[] = []

    // Navigate to section
    list.push({
        label: 'Go to Section',
        icon: 'pi pi-arrow-up-right',
        show: (row) => !!row.section,
        handler: (row) => router.visit(
            route('sections.show', {
                section: sectionId(row),
                tab: 'class-levels',
            })
        ),
    })

    if (hasPermission('class-levels.update')) {
        list.push({
            label: 'Edit',
            icon: 'pi pi-pencil',
            show: (row) => !row.deleted_at,
            handler: (row) => openFormModal(row),
        })

        list.push({
            label: (row) => row.is_active ? 'Deactivate' : 'Activate',
            icon: (row) => row.is_active ? 'pi pi-ban' : 'pi pi-check-circle',
            show: (row) => !row.deleted_at,
            handler: (row) => toggleActive(row),
            confirm: {
                message: (row) => row.is_active
                    ? `Deactivate "${row.name}"?`
                    : `Activate "${row.name}"?`,
                header: 'Confirm Status Change',
            },
        })
    }

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
                { url: destroyRoute(row), onSuccess: refresh }
            ),
            confirm: {
                message: (row) => `Delete "${row.name}"? This can be undone from the trash.`,
                header: 'Delete Class Level',
                acceptClass: 'p-button-danger',
            },
        })
    }

    if (hasPermission('class-levels.restore')) {
        list.push({
            label: 'Restore',
            icon: 'pi pi-undo',
            severity: 'success',
            show: (row) => !!row.deleted_at,
            handler: (row) => restoreResource(
                'class-levels',
                [row.id],
                { url: restoreRoute(row), onSuccess: refresh }
            ),
        })
    }

    if (hasPermission('class-levels.force-delete')) {
        list.push({
            label: 'Delete Permanently',
            icon: 'pi pi-times-circle',
            severity: 'danger',
            show: (row) => !!row.deleted_at,
            handler: (row) => deleteResource(
                'class-levels',
                [row.id],
                { url: forceDelRoute(row), onSuccess: refresh }
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

/**
 * Bulk delete on the global page is limited to rows from the SAME section
 * because the destroy endpoint is section-scoped.
 * We disable bulk delete when selected rows span multiple sections.
 */
const bulkActions = computed<BulkAction<ClassLevel>[]>(() => {
    if (!hasPermission('class-levels.delete')) return []

    return [
        {
            label: 'Delete Selected',
            icon: 'pi pi-trash',
            severity: 'danger',
            visible: (rows) => rows.length > 0 && !rows.some(r => r.deleted_at),
            disabled: false,
            handler: (rows) => {
                const sectionIds = new Set(rows.map(r => r.section?.id))

                // Block if rows span multiple sections
                if (sectionIds.size > 1) {
                    import('primevue/usetoast').then(({ useToast }) => {
                        useToast().add({
                            severity: 'warn',
                            summary: 'Multiple Sections',
                            detail: 'Please select levels from the same section only to bulk delete.',
                            life: 5000,
                        })
                    })
                    return
                }

                const firstRow = rows[0]
                deleteResource(
                    'class-levels',
                    rows.map(r => r.id),
                    { url: destroyRoute(firstRow), onSuccess: refresh }
                )
            },
            confirm: {
                message: (rows) => `Delete ${rows.length} class level(s)? This can be undone from the trash.`,
                header: 'Delete Class Levels',
                acceptClass: 'p-button-danger',
            },
        },
    ]
})

// ── Modal helpers ─────────────────────────────────────────────────────────────

const openFormModal = (classLevel: ClassLevel) => {
    if (!classLevel.section) return

    const instance = modal.open('class-level-form', {
        section: classLevel.section,
        classLevel: classLevel,
    })

    instance.on('saved', refresh)
}
</script>

<template>
    <AuthenticatedLayout title="Class Levels" :crumb="[
        { label: 'Settings' },
        { label: 'Academic' },
        { label: 'Class Levels' },
    ]">
        <SettingsLayout>

            <!-- ── Settings Sidebar ───────────────────────────────────────── -->
            <template #left>
                <SettingsSidebar title="Academic Settings" :items="academicSettingsNav" />
            </template>

            <!-- ── Main content ───────────────────────────────────────────── -->
            <template #main>

                <!-- Page header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Class Levels
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        View and manage class levels across all sections.
                        To create or bulk-generate levels, visit the

                        <a :href="route('sections.index')" class="text-primary hover:underline">
                            section detail page
                        </a>.
                    </p>
                </div>

                <!-- ── Stats bar ──────────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div v-for="(stat, key) in [
                        { label: 'Total Levels', value: stats.total, icon: 'pi pi-list', color: 'text-primary' },
                        { label: 'Active', value: stats.active, icon: 'pi pi-check-circle', color: 'text-green-600' },
                        { label: 'Inactive', value: stats.inactive, icon: 'pi pi-ban', color: 'text-gray-400' },
                        { label: 'Sections', value: stats.sections, icon: 'pi pi-sitemap', color: 'text-blue-500' },
                    ]" :key="key"
                        class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                        <i :class="[stat.icon, stat.color, 'text-xl']" aria-hidden="true" />
                        <div>
                            <div class="text-lg font-bold text-gray-900 dark:text-white leading-none">
                                {{ stat.value }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ stat.label }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Section filter ─────────────────────────────────────── -->
                <div class="flex items-center gap-3 mb-4">
                    <label for="section-filter"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        Filter by section:
                    </label>
                    <Select id="section-filter" v-model="selectedSectionId"
                        :options="[{ id: null, name: 'All sections' }, ...sections]" option-label="name"
                        option-value="id" placeholder="All sections" class="w-56" show-clear @change="applyFilter" />
                </div>

                <!-- ── DataTable ──────────────────────────────────────────── -->
                <AdvancedDataTable ref="tableRef" :endpoint="endpoint" :columns="columns" :actions="actions"
                    :bulk-actions="bulkActions" :initial-params="initialParams" :initial-data="classLevels.data"
                    :total-records="classLevels.totalRecords" data-property="data" />
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
