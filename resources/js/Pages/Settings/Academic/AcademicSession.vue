<script setup lang="ts">
import { computed, markRaw } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useModal } from '@/composables/useModal'
import SessionStatusBadge from '@/Components/Academic/Session/SessionStatusBadge.vue'
import CurrentSessionBanner from '@/Components/Academic/Session/CurrentSessionBanner.vue'
import type { AcademicSession } from '@/types/academic'
import type { TableAction, BulkAction } from '@/types/datatables'

const props = defineProps<{
    sessions: any
    totalRecords: number
    currentPage: number
    lastPage: number
    perPage: number
    columns: any[]
    globalFilterables: any[]
    filters: Record<string, any>
    error?: string
}>()

const { hasPermission } = usePermissions()
const modal = useModal()

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

const activateSession = (row: AcademicSession) => lifecycleAction('activate', row)
const planSession = (row: AcademicSession) => lifecycleAction('plan', row)
const pauseSession = (row: AcademicSession) => lifecycleAction('pause', row)
const resumeSession = (row: AcademicSession) => lifecycleAction('resume', row)
const closeSession = (row: AcademicSession) => lifecycleAction('close', row)
const reopenSession = (row: AcademicSession) => lifecycleAction('reopen', row)

const isCurrentOperational = (row: AcademicSession) =>
    row.state === 'active' || row.state === 'paused'

const deleteSession = async (row: AcademicSession) => {
    const canForce = !row.terms_count && !isCurrentOperational(row)
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
    // deleteResource handled by parent table patterns
}

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
        show: (row) => hasPermission('academic-sessions.delete') && !isCurrentOperational(row),
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
            <!-- Data table consumes sessions, columns, tableActions via existing AdvancedDataTable patterns -->
            <p v-if="error" class="text-red-600">{{ error }}</p>
        </div>
    </AuthenticatedLayout>
</template>
