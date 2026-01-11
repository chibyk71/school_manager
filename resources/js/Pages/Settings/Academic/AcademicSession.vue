<!--
resources/js/Pages/Settings/Academic/AcademicSession/Index.vue
================================================================================

Main listing & management screen for Academic Sessions.

This page uses the server-prepared data & columns coming from the controller's
tableQuery() trait result + AcademicSessionResource transformation.

Key characteristics:
────────────────────────────────────────────────────────────────────────────────
• Fully server-driven: consumes pre-generated columns & data from backend
• Shows current active session banner (assumes shared via Inertia::share)
• Permission-aware UI (create/edit/activate/close/delete + view terms)
• Bulk actions via AdvancedDataTable (delete/restore)
• Row actions open appropriate modals (including new "View Terms" modal)
• Responsive, accessible, clean Tailwind + PrimeVue styling

Backend alignment:
────────────────────────────────────────────────────────────────────────────────
Controller sends:
- sessions:           AcademicSessionResource[] (transformed collection)
- columns:            array of ColumnDefinition (auto-generated)
- totalRecords, currentPage, lastPage, perPage
- globalFilterables, filters
- (optionally) currentSession (if shared globally)

New feature added:
────────────────────────────────────────────────────────────────────────────────
• "View Terms" action button per row → opens SessionTermsModal.vue
  - Passes the selected session so the modal can show terms for that session
  - Permission: 'terms.index' (you can adjust)

Future steps:
• Build SessionTermsModal.vue (small DataTable + term actions)
• Build TermFormModal.vue, CloseTermModal.vue, DeleteTermModal.vue
-->

<script setup lang="ts">
import { computed, markRaw } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useModal } from '@/composables/useModal'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import { useRestoreResource } from '@/composables/useRestoreResource'
import type { AcademicSession } from '@/types/academic'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import SessionStatusBadge from '@/Components/Academic/Session/SessionStatusBadge.vue'
import CurrentSessionBanner from '@/Components/Academic/Session/CurrentSessionBanner.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps<{
    sessions: AcademicSession[]    // transformed collection
    totalRecords: number
    currentPage: number
    lastPage: number
    perPage: number
    columns: ColumnDefinition<any>[]           // auto-generated from trait
    globalFilterables: string[]
}>()

const modal = useModal()
const { hasPermission } = usePermissions()

const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

// ────────────────────────────────────────────────
// Computed props from Inertia
// ────────────────────────────────────────────────
const sessions = computed(() => props.sessions || [])
const columns = computed(() => props.columns || [])
const currentSession = computed(() => usePage().props.currentSession || null)

// ────────────────────────────────────────────────
// Quick action handlers
// ────────────────────────────────────────────────

const createNew = () => {
    modal.open('session-form', { mode: 'create' })
}

const editSession = (row: AcademicSession) => {
    modal.open('session-form', { session: row, mode: 'edit' })
}

const activateSession = async (row: AcademicSession) => {
    const modalInstance = modal.open('activate-session', { session: row }, { async: true })
    modalInstance.on('confirmed', () => router.reload({ only: ['sessions', 'currentSession'] }))
}

const closeSession = async (row: AcademicSession) => {
    const modalInstance = modal.open('close-session', { session: row }, { async: true })
    modalInstance.on('confirmed', () => router.reload({ only: ['sessions', 'currentSession'] }))
}

const deleteSession = async (row: AcademicSession) => {
    const canForce = !row.terms_count && !row.is_current

    const result = modal.open('delete-session', {
        session: row,
        canForceDelete: canForce
    }, { async: true })

    result?.on('deleted', () => router.reload({ only: ['sessions'] }))
}

// ────────────────────────────────────────────────
// NEW: View Terms in this session
// ────────────────────────────────────────────────
const viewTerms = (row: AcademicSession) => {
    modal.open('session-terms', {
        session: row
    })
}

// ────────────────────────────────────────────────
// Bulk action handlers
// ────────────────────────────────────────────────

const handleBulkDelete = (selectedRows: AcademicSession[]) => {
    const ids = selectedRows.map(row => row.id)
    deleteResource('academic-sessions', ids, {
        onSuccess: () => router.reload({ only: ['sessions'] })
    })
}

const handleBulkRestore = (selectedRows: any[]) => {
    const ids = selectedRows.map(row => row.id)
    restoreResource('academic-sessions', ids, {
        onSuccess: () => router.reload({ only: ['sessions'] })
    })
}

// ────────────────────────────────────────────────
// Bulk action definitions
// ────────────────────────────────────────────────

const bulkActions = computed<BulkAction<AcademicSession>[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        visible: (rows) => rows.length > 0 && hasPermission('academic-sessions.delete'),
        handler: handleBulkDelete
    },
    {
        label: 'Restore Selected',
        icon: 'pi pi-refresh',
        severity: 'success',
        visible: (rows) => rows.length > 0 && rows.every((session) => !!session.deleted_at),
        handler: handleBulkRestore
    }
])

// ────────────────────────────────────────────────
// Enhanced columns (status rendering)
// ────────────────────────────────────────────────

const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = [...props.columns]; // Clone to avoid mutation

    const upsert = (field: string, newCol: Partial<ColumnDefinition<any>>) => {
        const index = cols.findIndex(c => c.field === field);
        if (index >= 0) {
            cols[index] = { ...cols[index], ...newCol };
        } else {
            cols.push({ field, header: field, ...newCol } as ColumnDefinition<any>);
        }
    };

    upsert('status', {
        sortable: true,
        align: 'center',
        width: '100px',
        render: (row: any) => ({
            component: markRaw(SessionStatusBadge) as any,
            props: { status: row.status, isCurrent: row.is_current },
        }),
    });

    // Optional: make term_count clickable if you want (alternative navigation)
    upsert('terms_count', {
        header: 'Terms',
        sortable: true,
        align: 'center',
        render: (row) => ({
            template: `<span class=''>${row.terms_count > 0 ? row.terms_count : '—'}</span>`
        })
    });

    return cols;
})

// ────────────────────────────────────────────────
// Row actions (now includes View Terms)
// ────────────────────────────────────────────────

const tableActions = computed<TableAction<AcademicSession>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: () => hasPermission('academic-sessions.edit'),
        handler: (row) => editSession(row),
    },
    {
        label: 'Activate',
        icon: 'pi pi-check-circle',
        severity: 'success',
        show: (row) => hasPermission('academic-sessions.activate') && row.status === 'pending' && !row.is_current,
        handler: (row) => activateSession(row),
    },
    {
        label: 'Close Session',
        icon: 'pi pi-lock',
        severity: 'warn',
        show: (row) => hasPermission('academic-sessions.close') && row.status === 'active' && !row.is_current,
        handler: (row) => closeSession(row),
    },
    {
        label: 'View Terms',
        icon: 'pi pi-list',
        severity: 'info',
        show: (row) => hasPermission('terms.index') && (row.terms_count ?? 0) > 0,
        handler: (row) => viewTerms(row),
    },
    {
        label: 'Delete Session',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (row) => hasPermission('academic-sessions.delete') && !row.is_current,
        handler: (row) => deleteSession(row),
    }
])
</script>

<template>
    <AuthenticatedLayout title="Academic Sessions"
        :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Academic Sessions' }]" :buttons="[{
            label: 'Create New Session',
            icon: 'pi pi-plus',
            onClick: createNew,
            class: { 'hidden': !hasPermission('academic-sessions.create') }
        }]">
        <!-- Current active session banner -->
        <CurrentSessionBanner v-if="currentSession" :session="currentSession"
            :showLink="hasPermission('academic-sessions.index')" />

        <!-- Main Data Table -->
        <AdvancedDataTable :endpoint="route('academic-sessions.index')" :columns="enhancedColumns"
            :totalRecords="totalRecords" :initial-data="sessions" :bulkActions="bulkActions" :actions="tableActions">
            <!-- Empty state -->
            <template #empty>
                <div class="text-center py-12">
                    <i class="pi pi-calendar-times text-6xl text-gray-400 mb-4"></i>
                    <p class="text-lg font-medium text-gray-600 dark:text-gray-300">
                        No academic sessions found
                    </p>
                    <p class="mt-2 text-gray-500">
                        Start by creating your first academic year
                    </p>
                </div>
            </template>
        </AdvancedDataTable>
    </AuthenticatedLayout>
</template>
