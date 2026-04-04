<!--
  resources/js/Pages/Settings/Academic/Subjects.vue – v1.0

  ─────────────────────────────────────────────────────────────────────────────
  WHAT IT IMPLEMENTS
  ─────────────────────────────────────────────────────────────────────────────
  Full subjects management page using the AdvancedDataTable component with:
  • Server-side DataTable (search, filter, sort, paginate via HasTableQuery)
  • Column enhancements: active badge, type/category badges, section chips, color dot
  • Bulk actions: delete, restore, activate, deactivate, permanent delete
  • Create/edit via SubjectModal
  • Show-trashed toggle (view deleted subjects and restore them)
  • All standard patterns from the codebase (useDeleteResource, useRestoreResource,
    useEnhancedColumns, ActionsDropdown, AuthenticatedLayout)

  ─────────────────────────────────────────────────────────────────────────────
  FEATURES / PROBLEMS SOLVED
  ─────────────────────────────────────────────────────────────────────────────
  • Active/inactive badge displayed inline with color coding
  • Subject type badge (Core = blue, Elective = amber, Optional = slate)
  • Category badge (Sciences = emerald, Arts = violet, Commerce = orange, etc.)
  • Color dot column showing the timetable color swatch
  • Section names displayed as comma-separated tags (truncated if many)
  • Bulk activate/deactivate via toggle action
  • Trashed view: shows deleted subjects with restore and force-delete options
  • Modal opens in correct mode (create vs edit) based on selected row
  • Refresh after any CRUD operation (table.refresh())
  • Loading states, empty states, error handling

  ─────────────────────────────────────────────────────────────────────────────
  FITS INTO THE MODULE
  ─────────────────────────────────────────────────────────────────────────────
  • Route: GET /settings/academic/subjects  → settings.academic.subjects.index
  • Layout: AuthenticatedLayout
  • DataTable: AdvancedDataTable (from codebase)
  • Modal: SubjectModal.vue (create/edit)
  • Bulk: useDeleteResource, useRestoreResource (from codebase)
-->

<script setup lang="ts">
import { ref, computed, h } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { Badge, Button, Tag } from 'primevue'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import SubjectModal from '@/Components/Modals/Academic/SubjectModal.vue'

import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'

import type { Subject, SubjectsPageProps, SelectOption } from '@/types/subject'
import type { TableAction, BulkAction, ColumnDefinition } from '@/types/datatables'
import axios from 'axios'

// ─── Page props ───────────────────────────────────────────────────────────
const props = defineProps<SubjectsPageProps>()

const toast = useToast()
const confirm = useConfirm()

// ─── DataTable ref ────────────────────────────────────────────────────────
const tableRef = ref<{ refresh: () => void; exportData: (all?: boolean, visible?: boolean) => void } | null>(null)

// ─── Modal state ──────────────────────────────────────────────────────────
const modalVisible = ref(false)
const editingSubject = ref<Subject | null>(null)

const openCreate = () => {
    editingSubject.value = null
    modalVisible.value = true
}

const openEdit = (subject: Subject) => {
    editingSubject.value = subject
    modalVisible.value = true
}

const onModalSaved = () => {
    tableRef.value?.refresh()
}

// ─── Trashed toggle ───────────────────────────────────────────────────────
const showTrashed = ref(false)

const toggleTrashed = () => {
    showTrashed.value = !showTrashed.value
    tableRef.value?.refresh()
}

// ─── Delete / Restore composables ────────────────────────────────────────
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

// ─── Bulk activate / deactivate ───────────────────────────────────────────
const bulkToggle = async (selectedRows: Subject[], isActive: boolean) => {
    const ids = selectedRows.map(r => r.id)
    const label = isActive ? 'activate' : 'deactivate'

    confirm.require({
        header: `${isActive ? 'Activate' : 'Deactivate'} Subjects`,
        message: `Are you sure you want to ${label} ${ids.length} subject(s)?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: isActive ? 'Activate' : 'Deactivate',
        acceptProps: { severity: isActive ? 'success' : 'warning' },
        rejectProps: { severity: 'secondary', outlined: true },
        accept: async () => {
            try {
                const resp = await axios.post(route('settings.academic.subjects.toggle'), {
                    ids,
                    is_active: isActive,
                })
                toast.add({
                    severity: 'success',
                    summary: 'Done',
                    detail: resp.data.message,
                    life: 4000,
                })
                tableRef.value?.refresh()
            } catch (e: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: e.response?.data?.message ?? 'Operation failed.',
                    life: 5000,
                })
            }
        },
    })
}

// ─── Row actions ──────────────────────────────────────────────────────────
const rowActions = computed<TableAction<Subject>[]>(() => {
    if (showTrashed.value) {
        // Trashed mode: restore + force-delete only
        return [
            {
                label: 'Restore',
                icon: 'pi pi-replay',
                handler: (row) => restoreResource('settings.academic.subjects', [row.id], {
                    onSuccess: () => tableRef.value?.refresh(),
                }),
            },
            {
                label: 'Delete Permanently',
                icon: 'pi pi-trash',
                severity: 'danger',
                confirm: {
                    message: (row) => `Permanently delete "${row.name}"? This cannot be undone.`,
                    header: 'Permanent Deletion',
                    icon: 'pi pi-exclamation-triangle',
                    acceptClass: 'p-button-danger',
                },
                handler: async (row) => {
                    try {
                        const resp = await axios.delete(route('settings.academic.subjects.force'), {
                            data: { ids: [row.id] },
                        })
                        toast.add({ severity: 'success', summary: 'Deleted', detail: resp.data.message, life: 4000 })
                        tableRef.value?.refresh()
                    } catch (e: any) {
                        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message ?? 'Failed.', life: 5000 })
                    }
                },
            },
        ]
    }

    return [
        {
            label: 'Edit',
            icon: 'pi pi-pencil',
            handler: (row) => openEdit(row),
        },
        {
            label: (row) => row.is_active ? 'Deactivate' : 'Activate',
            icon: (row) => row.is_active ? 'pi pi-eye-slash' : 'pi pi-eye',
            handler: async (row) => {
                try {
                    const resp = await axios.post(route('settings.academic.subjects.toggle'), {
                        ids: [row.id],
                        is_active: !row.is_active,
                    })
                    toast.add({ severity: 'success', summary: 'Updated', detail: resp.data.message, life: 3000 })
                    tableRef.value?.refresh()
                } catch {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Could not update status.', life: 4000 })
                }
            },
        },
        {
            label: 'Delete',
            icon: 'pi pi-trash',
            severity: 'danger',
            confirm: {
                message: (row) => `Delete "${row.name}"? It can be restored later.`,
                header: 'Confirm Deletion',
                icon: 'pi pi-exclamation-triangle',
                acceptClass: 'p-button-danger',
            },
            handler: (row) => deleteResource('settings.academic.subjects', [row.id], {
                onSuccess: () => tableRef.value?.refresh(),
            }),
        },
    ]
})

// ─── Bulk actions ─────────────────────────────────────────────────────────
const bulkActions = computed<BulkAction<Subject>[]>(() => {
    if (showTrashed.value) {
        return [
            {
                label: 'Restore Selected',
                icon: 'pi pi-replay',
                severity: 'success',
                action: 'restore',
                handler: (rows) => restoreResource('settings.academic.subjects', rows.map(r => r.id), {
                    onSuccess: () => tableRef.value?.refresh(),
                }),
            },
        ]
    }

    return [
        {
            label: 'Activate',
            icon: 'pi pi-check-circle',
            severity: 'success',
            action: 'activate',
            handler: (rows) => bulkToggle(rows, true),
        },
        {
            label: 'Deactivate',
            icon: 'pi pi-ban',
            severity: 'warning',
            action: 'deactivate',
            handler: (rows) => bulkToggle(rows, false),
        },
        {
            label: 'Delete',
            icon: 'pi pi-trash',
            severity: 'danger',
            action: 'delete',
            confirm: {
                message: (rows) => `Delete ${rows.length} subject(s)? They can be restored later.`,
                header: 'Bulk Delete',
                icon: 'pi pi-exclamation-triangle',
                acceptClass: 'p-button-danger',
            },
            handler: (rows) => deleteResource('settings.academic.subjects', rows.map(r => r.id), {
                onSuccess: () => tableRef.value?.refresh(),
            }),
        },
    ]
})

// ─── Column enhancements ──────────────────────────────────────────────────
const { enhancedColumns } = useEnhancedColumns<Subject>(
    props.columns as ColumnDefinition<Subject>[],
    {
        name: {
            render: (row) => h('div', { class: 'flex items-center gap-2' }, [
                // Color dot
                row.color
                    ? h('span', {
                        class: 'w-2.5 h-2.5 rounded-full shrink-0 ring-1 ring-white dark:ring-gray-700 shadow-sm',
                        style: { backgroundColor: row.color },
                    })
                    : null,
                h('span', { class: 'font-medium text-gray-900 dark:text-gray-100' }, row.name),
            ]),
        },

        code: {
            render: (row) => h(
                'span',
                { class: 'font-mono text-xs font-semibold tracking-widest bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded' },
                row.code
            ),
        },

        type: {
            render: (row) => {
                const config: Record<string, { severity: string; class: string }> = {
                    core: { severity: 'info', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
                    elective: { severity: 'warn', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
                    optional: { severity: 'secondary', class: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' },
                }
                const { class: cls } = config[row.type] ?? config.optional

                return h(
                    'span',
                    { class: `inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}` },
                    row.type_label
                )
            },
        },

        category: {
            render: (row) => {
                const colors: Record<string, string> = {
                    sciences: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                    arts: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                    commerce: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                    languages: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                    technical: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                    general: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                }

                return h(
                    'span',
                    { class: `inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${colors[row.category] ?? colors.general}` },
                    row.category_label
                )
            },
        },

        is_active: {
            render: (row) => h(
                'span',
                {
                    class: row.is_active
                        ? 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                        : 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                },
                [
                    h('i', { class: row.is_active ? 'pi pi-check-circle text-[10px]' : 'pi pi-times-circle text-[10px]' }),
                    row.is_active ? 'Active' : 'Inactive',
                ]
            ),
        },

        school_section_names: {
            header: 'Sections',
            render: (row) => {
                if (!row.school_section_names) {
                    return h('span', { class: 'text-gray-400 dark:text-gray-600 text-xs' }, '—')
                }
                const names = row.school_section_names.split(', ')
                return h('div', { class: 'flex flex-wrap gap-1' },
                    names.slice(0, 3).map(n =>
                        h('span', {
                            class: 'text-xs px-1.5 py-0.5 rounded bg-primary/10 text-primary dark:bg-primary/20 font-medium',
                        }, n)
                    ).concat(
                        names.length > 3
                            ? [h('span', { class: 'text-xs text-gray-500 dark:text-gray-400' }, `+${names.length - 3} more`)]
                            : []
                    )
                )
            },
        },

        deleted_at: {
            header: 'Deleted',
            render: (row) => row.deleted_at
                ? h('span', { class: 'text-xs text-red-600 dark:text-red-400' }, row.deleted_at)
                : h('span', { class: 'text-gray-400' }, '—'),
        },
    }
)

// ─── Page header buttons ──────────────────────────────────────────────────
const pageButtons = computed(() => [
    {
        label: showTrashed.value ? 'Show Active' : 'Show Deleted',
        icon: showTrashed.value ? 'pi pi-list' : 'pi pi-trash',
        severity: 'secondary',
        outlined: true,
        onClick: toggleTrashed,
    },
    {
        label: 'Add Subject',
        icon: 'pi pi-plus',
        onClick: openCreate,
    },
])
</script>

<template>
    <AuthenticatedLayout title="Subjects" :crumb="crumbs">
        <!-- Page header action buttons -->
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subjects</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Manage academic subjects, their types, categories, and assignments.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Trashed toggle -->
                    <Button :label="showTrashed ? 'Show Active' : 'Show Deleted'"
                        :icon="showTrashed ? 'pi pi-list' : 'pi pi-trash'" severity="secondary" outlined size="small"
                        @click="toggleTrashed" />

                    <!-- Add Subject -->
                    <Button v-if="!showTrashed" label="Add Subject" icon="pi pi-plus" @click="openCreate" />
                </div>
            </div>

            <!-- Trashed mode notice -->
            <div v-if="showTrashed"
                class="mb-4 flex items-center gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-amber-800 dark:text-amber-300 text-sm">
                <i class="pi pi-exclamation-triangle text-base" />
                <span>
                    Showing <strong>deleted subjects</strong>.
                    Select subjects to restore them or permanently remove them.
                </span>
            </div>
        </template>

        <!-- DataTable -->
        <AdvancedDataTable ref="tableRef" :endpoint="route('settings.academic.subjects.index')"
            :initial-data="initialData" :total-records="totalRecords" :columns="enhancedColumns"
            :bulk-actions="bulkActions" :actions="rowActions" :global-filter-fields="globalFilterables"
            :initial-params="showTrashed ? { with_trashed: true } : {}" data-key="id" />

        <!-- Create / Edit Modal -->
        <SubjectModal v-model:visible="modalVisible" :subject="editingSubject" :school-sections="schoolSections"
            :class-levels="classLevels" :subject-types="subjectTypes" :subject-categories="subjectCategories"
            @saved="onModalSaved" />
    </AuthenticatedLayout>
</template>
