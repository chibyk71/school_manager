<!--
resources/js/Pages/Settings/Academic/AcademicSession/Index.vue
================================================================================
Main listing & management screen for Academic Sessions.
Phase 2: lifecycle actions (plan/activate/pause/resume/close/reopen); no Set Current.
Preserves AdvancedDataTable, bulk delete/restore, columns, filtering.
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
    sessions: AcademicSession[]
    totalRecords: number
    currentPage: number
    lastPage: number
    perPage: number
    columns: ColumnDefinition<any>[]
    globalFilterables: string[]
}>()

const modal = useModal()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const { restoreResource } = useRestoreResource()

const sessions = computed(() => props.sessions || [])
const columns = computed(() => props.columns || [])
const currentSession = computed(() => usePage().props.currentSession || null)

const createNew = () => {
    modal.open('session-form', { mode: 'create' })
}

const editSession = (row: AcademicSession) => {
    modal.open('session-form', { session: row, mode: 'edit' })
}

const lifecycleAction = (action: string, row: AcademicSession) => {
    router.patch(route(`academic-sessions.${action}`, row.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['sessions', 'currentSession'] }),
        onError: (errors) => {
            const msg = Object.values(errors || {}).flat()[0] || `Failed to ${action} session`
            console.error(msg)
        },
    })
}

const activateSession = (row: AcademicSession) => {
    try {
        const modalInstance = modal.open('activate-session', { session: row }, { async: true })
        modalInstance.on('confirmed', () => lifecycleAction('activate', row))
    } catch {
        lifecycleAction('activate', row)
    }
}

const planSession = (row: AcademicSession) => lifecycleAction('plan', row)
const pauseSession = (row: AcademicSession) => lifecycleAction('pause', row)
const resumeSession = (row: AcademicSession) => lifecycleAction('resume', row)
const reopenSession = (row: AcademicSession) => lifecycleAction('reopen', row)

const closeSession = (row: AcademicSession) => {
    try {
        const modalInstance = modal.open('close-session', { session: row }, { async: true })
        modalInstance.on('confirmed', () => lifecycleAction('close', row))
    } catch {
        lifecycleAction('close', row)
    }
}

const isCurrentOperational = (row: AcademicSession) =>
    row.state === 'active' || row.state === 'paused'

const deleteSession = async (row: AcademicSession) => {
    const canForce = !row.terms_count && !isCurrentOperational(row) && row.state !== 'closed'
    const result = modal.open('delete-session', {
        session: row,
        canForceDelete: canForce
    }, { async: true })
    result?.on('deleted', () => router.reload({ only: ['sessions'] }))
}

const viewTerms = (row: AcademicSession) => {
    modal.open('session-terms', { session: row })
}

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

const bulkActions = computed<BulkAction<AcademicSession>[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: () => hasPermission('academic-sessions.delete'),
        handler: handleBulkDelete,
    },
    {
        label: 'Restore Selected',
        icon: 'pi pi-refresh',
        severity: 'info',
        show: () => hasPermission('academic-sessions.restore'),
        handler: handleBulkRestore,
    },
])

const enhancedColumns = computed(() => {
    const cols = [...(columns.value || [])]
    const upsert = (field: string, def: any) => {
        const idx = cols.findIndex((c: any) => c.field === field || c.key === field)
        if (idx >= 0) cols[idx] = { ...cols[idx], ...def }
        else cols.push({ field, ...def })
    }
    upsert('state', {
        header: 'Status',
        sortable: true,
        render: (row: any) => ({
            component: markRaw(SessionStatusBadge) as any,
            props: { status: row.state ?? row.status, isCurrent: row.state === 'active' || row.state === 'paused' },
        }),
    })
    upsert('terms_count', {
        header: 'Terms',
        sortable: true,
        align: 'center',
        render: (row: any) => ({
            template: `<span>${row.terms_count > 0 ? row.terms_count : '—'}</span>`
        })
    })
    return cols
})

const tableActions = computed<TableAction<AcademicSession>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: () => hasPermission('academic-sessions.update') || hasPermission('academic-sessions.edit'),
        handler: (row) => editSession(row),
    },
    {
        label: 'Plan',
        icon: 'pi pi-calendar',
        severity: 'info',
        show: (row) => hasPermission('academic-sessions.update') && row.state === 'draft',
        handler: (row) => planSession(row),
    },
    {
        label: 'Activate',
        icon: 'pi pi-check-circle',
        severity: 'success',
        show: (row) => hasPermission('academic-sessions.update') && (row.state === 'draft' || row.state === 'planned'),
        handler: (row) => activateSession(row),
    },
    {
        label: 'Pause',
        icon: 'pi pi-pause',
        severity: 'warn',
        show: (row) => hasPermission('academic-sessions.update') && row.state === 'active',
        handler: (row) => pauseSession(row),
    },
    {
        label: 'Resume',
        icon: 'pi pi-play',
        severity: 'success',
        show: (row) => hasPermission('academic-sessions.update') && row.state === 'paused',
        handler: (row) => resumeSession(row),
    },
    {
        label: 'Close',
        icon: 'pi pi-lock',
        severity: 'warn',
        show: (row) => hasPermission('academic-sessions.update') && (row.state === 'active' || row.state === 'paused'),
        handler: (row) => closeSession(row),
    },
    {
        label: 'Reopen',
        icon: 'pi pi-replay',
        severity: 'info',
        show: (row) => hasPermission('academic-sessions.update') && row.state === 'closed',
        handler: (row) => reopenSession(row),
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
        show: (row) => hasPermission('academic-sessions.delete') && !isCurrentOperational(row) && row.state !== 'closed',
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
        <CurrentSessionBanner v-if="currentSession" :session="currentSession"
            :showLink="hasPermission('academic-sessions.index')" />

        <div class="mt-4">
            <AdvancedDataTable
                :data="sessions"
                :columns="enhancedColumns"
                :actions="tableActions"
                :bulk-actions="bulkActions"
                :total-records="totalRecords"
                :current-page="currentPage"
                :last-page="lastPage"
                :per-page="perPage"
                :global-filterables="globalFilterables"
                resource-name="academic-sessions"
            />
        </div>
    </AuthenticatedLayout>
</template>
