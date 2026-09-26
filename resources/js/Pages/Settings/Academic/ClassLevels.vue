<script setup lang="ts">
/**
 * Pages/Settings/Academic/ClassLevels.vue
 * Restored from master; Phase 5: removed total-records, data-property.
 * Global class levels management under Settings → Academic.
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

const { academicSettingsNav } = useSettingsNavigation()
const modal = useModal()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()
const { hasPermission } = usePermissions()

const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)
const refresh = () => tableRef.value?.refresh()

const selectedSectionId = ref<string | null>(props.filters.section_id ?? null)

const applyFilter = () => {
    router.get(
        route('settings.academic.class-levels.index'),
        { section_id: selectedSectionId.value ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}

const endpoint = route('settings.academic.class-levels.index')

const initialParams = computed(() => ({
    ...(selectedSectionId.value ? { section_id: selectedSectionId.value } : {}),
}))

const stats = computed(() => {
    const levels = props.classLevels.data
    return {
        total: props.classLevels.totalRecords,
        active: levels.filter(l => l.is_active && !l.deleted_at).length,
        inactive: levels.filter(l => !l.is_active && !l.deleted_at).length,
        sections: new Set(levels.map(l => l.section?.id).filter(Boolean)).size,
    }
})

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
            h('span', { class: 'font-medium text-gray-900 dark:text-white' }, row.name),
            row.alias
                ? h('span', { class: 'text-xs text-gray-400 dark:text-gray-500 font-mono' }, row.alias)
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
                h('span', { class: 'text-sm text-gray-700 dark:text-gray-300' }, row.section.name),
                h('a', {
                    href: route('sections.show', { section: row.section.id, tab: 'class-levels' }),
                    class: 'text-primary hover:text-primary/70 transition-colors',
                    title: `Go to ${row.section.name}`,
                    onClick: (e: MouseEvent) => {
                        e.preventDefault()
                        router.visit(route('sections.show', {
                            section: row?.section?.id,
                            tab: 'class-levels',
                        }))
                    },
                }, [h('i', { class: 'pi pi-arrow-up-right text-xs' })]),
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
            class: row.max_arms ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600 italic text-xs',
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

const sectionId = (row: ClassLevel) => row.section?.id ?? ''
const destroyRoute = (row: ClassLevel) => route('class-levels.destroy', sectionId(row))
const restoreRoute = (row: ClassLevel) => route('class-levels.restore', sectionId(row))
const forceDelRoute = (row: ClassLevel) => route('class-levels.force-delete', sectionId(row))
const updateRoute = (row: ClassLevel) => route('class-levels.update', {
    section: sectionId(row),
    classLevel: row.id,
})

const toggleActive = async (row: ClassLevel) => {
    try {
        await (await import('axios')).default.patch(updateRoute(row), { is_active: !row.is_active })
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

const actions = computed<TableAction<ClassLevel>[]>(() => {
    const list: TableAction<ClassLevel>[] = []

    list.push({
        label: 'Go to Section',
        icon: 'pi pi-arrow-up-right',
        show: (row) => !!row.section,
        handler: (row) => router.visit(route('sections.show', {
            section: sectionId(row),
            tab: 'class-levels',
        })),
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
                message: (row) => row.is_active ? `Deactivate "${row.name}"?` : `Activate "${row.name}"?`,
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
            handler: (row) => deleteResource('class-levels', [row.id], { url: destroyRoute(row), onSuccess: refresh }),
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
            handler: (row) => restoreResource('class-levels', [row.id], { url: restoreRoute(row), onSuccess: refresh }),
        })
    }

    if (hasPermission('class-levels.force-delete')) {
        list.push({
            label: 'Delete Permanently',
            icon: 'pi pi-times-circle',
            severity: 'danger',
            show: (row) => !!row.deleted_at,
            handler: (row) => deleteResource('class-levels', [row.id], { url: forceDelRoute(row), onSuccess: refresh }),
            confirm: {
                message: (row) => `Permanently delete "${row.name}"? This cannot be undone.`,
                header: 'Permanent Delete',
                acceptClass: 'p-button-danger',
            },
        })
    }

    return list
})

const bulkActions = computed<BulkAction<ClassLevel>[]>(() => {
    if (!hasPermission('class-levels.delete')) return []
    return [
        {
            label: 'Delete Selected',
            icon: 'pi pi-trash',
            severity: 'danger',
            visible: (rows) => rows.length > 0 && !rows.some(r => r.deleted_at),
            handler: (rows) => {
                const sectionIds = new Set(rows.map(r => r.section?.id))
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
                deleteResource('class-levels', rows.map(r => r.id), { url: destroyRoute(firstRow), onSuccess: refresh })
            },
            confirm: {
                message: (rows) => `Delete ${rows.length} class level(s)? This can be undone from the trash.`,
                header: 'Delete Class Levels',
                acceptClass: 'p-button-danger',
            },
        },
    ]
})

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
            <template #left>
                <SettingsSidebar title="Academic Settings" :items="academicSettingsNav" />
            </template>
            <template #main>
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Class Levels</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        School-wide overview of class levels across all sections.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-xl font-semibold">{{ stats.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-xs text-gray-500">Active</div>
                        <div class="text-xl font-semibold">{{ stats.active }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-xs text-gray-500">Inactive</div>
                        <div class="text-xl font-semibold">{{ stats.inactive }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-xs text-gray-500">Sections</div>
                        <div class="text-xl font-semibold">{{ stats.sections }}</div>
                    </div>
                </div>

                <div class="mb-4 flex items-center gap-3">
                    <label class="text-sm font-medium">Filter by section</label>
                    <Select
                        v-model="selectedSectionId"
                        :options="[{ id: null, name: 'All sections' }, ...sections]"
                        option-label="name"
                        option-value="id"
                        class="w-64"
                        @change="applyFilter"
                    />
                </div>

                <AdvancedDataTable
                    ref="tableRef"
                    :endpoint="endpoint"
                    :columns="columns"
                    :actions="actions"
                    :bulk-actions="bulkActions"
                    :initial-params="initialParams"
                    
                />
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
