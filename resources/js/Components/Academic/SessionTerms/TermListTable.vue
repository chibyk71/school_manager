<!--
resources/js/Components/Modals/SessionTermsModal.vue
================================================================================

Inner content for the "Manage Terms" modal – rendered inside global ResourceDialog.vue.

Purpose / Features:
────────────────────────────────────────────────────────────────────────────────
• Shows all terms for one parent Academic Session
• "Add New Term" button → prepends term-form modal
• Compact PrimeVue <DataTable> with row actions:
  - Edit (term-form)
  - Close (close-term)
  - Reopen (reopen-term) ← added as requested
  - Delete (uses useDeleteResource – soft/force)
• Uses **instance returned from prepend** for stable event listening
• Cleans up listeners properly (off() returned by .on())
• Permission-aware UI (actions hidden when not allowed)
• Handles loading/empty/error states
• Responsive, accessible, dark-mode ready

Integration:
────────────────────────────────────────────────────────────────────────────────
• Opened via: modal.prepend('session-terms', { session })
• Child modals emit 'saved'/'updated'/'closed'/'reopened' on success
• Listens using child instance reference → survives queue changes
• Assumes terms eager-loaded on session (with('terms'))

Backend Assumptions:
────────────────────────────────────────────────────────────────────────────────
• Session includes: id, name, start_date, end_date, is_current, terms[]
• Term fields: id, name, start_date, end_date, status, is_current
• Routes: terms.store, terms.update, terms.close, terms.reopen, terms.destroy
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { format, parseISO } from 'date-fns'
import { Button, Badge, ProgressSpinner, Message, DataTable, Column } from 'primevue'
import { useModal } from '@/composables/useModal'
import { useToast } from 'primevue/usetoast'
import { usePermissions } from '@/composables/usePermissions'
import SessionStatusBadge from '@/Components/Academic/Session/SessionStatusBadge.vue'
import { useDeleteResource } from '@/composables/useDelete'
import type { AcademicSession, Term } from '@/types/academic'

const modal = useModal()
const toast = useToast()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()

// ────────────────────────────────────────────────
// Props from modal payload
// ────────────────────────────────────────────────
const props = defineProps<{
    session: AcademicSession
}>()

const session = computed(() => props.session)
const initialTerms = computed(() => session.value?.terms || [])

// ────────────────────────────────────────────────
// Local state
// ────────────────────────────────────────────────
const terms = ref<Term[]>(initialTerms.value)
const loading = ref(false)
const error = ref<string | null>(null)

// ────────────────────────────────────────────────
// Computed
// ────────────────────────────────────────────────
const sortedTerms = computed(() =>
    [...terms.value].sort((a, b) =>
        parseISO(a.start_date).getTime() - parseISO(b.start_date).getTime()
    )
)

const hasTerms = computed(() => sortedTerms.value.length > 0)

const formatDate = (dateStr?: string) =>
    dateStr ? format(parseISO(dateStr), 'dd MMM yyyy') : '—'

// ────────────────────────────────────────────────
// Actions (open child modals)
// ────────────────────────────────────────────────
const openNewTerm = () => {
    const child = modal.prepend('term-form', {
        academic_session_id: props.session.id,
        mode: 'create'
    })

    const off = child.on('saved', (payload: Term) => {
        const index = terms.value.findIndex(t => t.id === payload.id)
        if (index !== -1) {
            terms.value[index] = payload
        } else {
            terms.value.push(payload)
        }
        toast.add({ severity: 'success', summary: 'Success', detail: 'Term created', life: 3000 })
        off() // cleanup listener
    })
}

const openEditTerm = (term: Term) => {
    const child = modal.prepend('term-form', {
        term,
        academic_session_id: props.session.id,
        mode: 'edit'
    })

    const off = child.on('saved', (payload: Term) => {
        const index = terms.value.findIndex(t => t.id === payload.id)
        if (index !== -1) terms.value[index] = payload
        toast.add({ severity: 'success', summary: 'Success', detail: 'Term updated', life: 3000 })
        off()
    })
}

const openCloseTerm = (term: Term) => {
    const child = modal.prepend('close-term', { term })

    const off = child.on('closed', (updatedTerm: Term) => {
        const index = terms.value.findIndex(t => t.id === updatedTerm.id)
        if (index !== -1) terms.value[index] = updatedTerm
        toast.add({ severity: 'success', summary: 'Success', detail: 'Term closed', life: 3000 })
        off()
    })
}

const openReopenTerm = (term: Term) => {
    const child = modal.prepend('reopen-term', { term })

    const off = child.on('reopened', (updatedTerm: Term) => {
        const index = terms.value.findIndex(t => t.id === updatedTerm.id)
        if (index !== -1) terms.value[index] = updatedTerm
        toast.add({ severity: 'success', summary: 'Success', detail: 'Term reopened', life: 3000 })
        off()
    })
}

const openDeleteTerm = (term: Term) => {
    deleteResource('terms', [term.id], {
        onSuccess: () => {
            terms.value = terms.value.filter(t => t.id !== term.id)
            toast.add({ severity: 'success', summary: 'Success', detail: 'Term deleted', life: 3000 })
        },
        onError: (err) => {
            toast.add({ severity: 'error', summary: 'Delete Failed', detail: err, life: 5000 })
        }
    })
}
</script>

<template>
    <div class="space-y-6">
        <!-- Session Header -->
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ session.name || 'Session Terms' }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ formatDate(session.start_date) }} — {{ formatDate(session.end_date) }}
                    </p>
                </div>
                <div v-if="session.is_current" class="self-start sm:self-center">
                    <Badge value="Current Session" severity="success" />
                </div>
            </div>
        </div>

        <!-- Add Button -->
        <div class="flex justify-end">
            <Button
                v-if="hasPermission('terms.create')"
                label="Add New Term"
                icon="pi pi-plus"
                severity="primary"
                @click="openNewTerm"
            />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
            <ProgressSpinner />
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading terms...</p>
        </div>

        <!-- Error -->
        <Message v-else-if="error" severity="error" :closable="false">
            {{ error }}
        </Message>

        <!-- Empty State -->
        <div
            v-else-if="!hasTerms"
            class="text-center py-12 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-dashed border-gray-300 dark:border-gray-600"
        >
            <i class="pi pi-calendar-minus text-5xl text-gray-400 mb-4"></i>
            <p class="text-lg font-medium text-gray-600 dark:text-gray-300">
                No terms defined yet
            </p>
            <p class="text-sm text-gray-500 mt-2">
                Click "Add New Term" to create one
            </p>
        </div>

        <!-- Terms Table -->
        <DataTable
            v-else
            :value="sortedTerms"
            tableStyle="min-width: 100%"
            class="p-datatable-sm"
            responsiveLayout="scroll"
        >
            <Column field="name" header="Term Name">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ data.name }}</span>
                        <Badge
                            v-if="data.is_current"
                            value="Current"
                            severity="success"
                            size="small"
                        />
                    </div>
                </template>
            </Column>

            <Column header="Dates">
                <template #body="{ data }">
                    {{ formatDate(data.start_date) }} — {{ formatDate(data.end_date) }}
                </template>
            </Column>

            <Column header="Status" style="width: 140px; text-align: center;">
                <template #body="{ data }">
                    <SessionStatusBadge
                        :status="data.status"
                        :is-current="data.is_current"
                        size="small"
                        entityType="term"
                    />
                </template>
            </Column>

            <Column header="Actions" style="width: 200px; text-align: right;">
                <template #body="{ data }">
                    <div class="flex items-center justify-end gap-1 flex-wrap">
                        <Button
                            v-if="hasPermission('terms.update')"
                            icon="pi pi-pencil"
                            rounded
                            text
                            severity="secondary"
                            size="small"
                            @click="openEditTerm(data)"
                            v-tooltip.top="'Edit Term'"
                        />

                        <Button
                            v-if="hasPermission('terms.close') && data.status === 'active'"
                            icon="pi pi-lock"
                            rounded
                            text
                            severity="warning"
                            size="small"
                            @click="openCloseTerm(data)"
                            v-tooltip.top="'Close Term'"
                        />

                        <Button
                            v-if="hasPermission('terms.reopen') && data.status === 'closed'"
                            icon="pi pi-refresh"
                            rounded
                            text
                            severity="success"
                            size="small"
                            @click="openReopenTerm(data)"
                            v-tooltip.top="'Reopen Term'"
                        />

                        <Button
                            v-if="hasPermission('terms.delete')"
                            icon="pi pi-trash"
                            rounded
                            text
                            severity="danger"
                            size="small"
                            @click="openDeleteTerm(data)"
                            v-tooltip.top="'Delete Term'"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<style scoped>
/* Compact table styling */
:deep(.p-datatable-sm .p-datatable-thead > tr > th) {
    @apply py-2 px-3 text-xs font-semibold bg-gray-100 dark:bg-gray-800;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr > td) {
    @apply py-2 px-3 text-sm;
}
</style>
