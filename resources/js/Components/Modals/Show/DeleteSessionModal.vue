<!--
resources/js/Components/Modals/DeleteSessionModal.vue
================================================================================

Confirmation modal for soft-deleting or force-deleting (permanently) an academic session.

CRITICAL SAFETY COMPONENT:
────────────────────────────────────────────────────────────────────────────────
Deleting academic sessions (especially permanent deletion) is a destructive action
with significant consequences:
- Soft delete: marks session as deleted (can be restored)
- Force delete: permanent removal + associated data (terms, results, etc.) may become orphaned
- Often irreversible without database-level recovery

This modal:
• Differentiates clearly between soft & permanent delete
• Requires explicit user choice when both options are available
• Shows strong warnings, affected data summary & confirmation phrase
• Prevents deletion of current/active session or sessions with dependencies
• Uses danger styling & double-confirmation pattern
• Supports async resolution → parent can await result
• Fully accessible, responsive, and production-ready

Integration:
────────────────────────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'delete-session'
• Opened via: modal.open('delete-session', { session, canForceDelete: boolean })
• Backend endpoints:
  - Soft delete:   DELETE  /academic-sessions/{id}
  - Force delete:  DELETE  /academic-sessions/{id}/force  (or with ?force=1)

Typical usage pattern:
────────────────────────────────────────────────────────────────────────────────
const result = await modal.open('delete-session', {
  session: selectedSession,
  canForceDelete: !session.terms_count && !session.is_current
}, { async: true })

if (result?.deleted) {
  // refresh table
}
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useModal } from '@/composables/useModal'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { format, parseISO } from 'date-fns'
import type { AcademicSession } from '@/types/academic'
import { Button, InputText, Message } from 'primevue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    session: AcademicSession
    canForceDelete?: boolean          // Whether permanent deletion is allowed
}>()

const modal = useModal()
const confirm = useConfirm()
const toast = useToast()

const deleteMode = ref<'soft' | 'force'>('soft')
const confirmationText = ref('')
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

// ────────────────────────────────────────────────
// Validation & State
// ────────────────────────────────────────────────

const isCurrent = computed(() => props.session.is_current === true)
const hasDependencies = computed(() => (props.session.terms_count ?? 0) > 0)

const canSoftDelete = computed(() =>
    !isCurrent.value && props.session.status !== 'archived'
)

const canPermanentDelete = computed(() =>
    props.canForceDelete !== false &&
    !isCurrent.value &&
    !hasDependencies.value
)

const dateRange = computed(() => {
    const start = format(parseISO(props.session.start_date), 'MMM yyyy')
    const end = format(parseISO(props.session.end_date), 'MMM yyyy')
    return `${start} — ${end}`
})

const isConfirmed = computed(() => {
    if (deleteMode.value === 'soft') return true
    return confirmationText.value.trim().toLowerCase() === 'delete permanently'
})

// ────────────────────────────────────────────────
// Delete Execution
// ────────────────────────────────────────────────

const performDelete = () => {
    confirm.require({
        message: `
      You are about to <strong>${deleteMode.value === 'force' ? 'PERMANENTLY' : ''} delete</strong>
      the academic session <strong>${props.session.name}</strong> (${dateRange.value}).

      ${deleteMode.value === 'force'
                ? 'This action <u>cannot be undone</u> and will permanently remove all associated data.'
                : 'This session will be moved to trash and can be restored later.'}

      ${isCurrent.value
                ? '<strong class="text-red-600">This is the current active session — deletion is blocked.</strong>'
                : ''}

      ${hasDependencies.value
                ? `<p class="mt-2">This session has ${props.session.terms_count} term(s). Consider removing terms first.</p>`
                : ''}

      Are you absolutely sure?
    `,
        header: deleteMode.value === 'force'
            ? 'Permanent Delete Warning'
            : 'Delete Academic Session',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancel',
        acceptLabel: `Yes, ${deleteMode.value === 'force' ? 'Permanently ' : ''}Delete`,
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-secondary p-button-outlined',
        accept: async () => {
            try {
                isSubmitting.value = true
                errorMessage.value = null

                await new Promise(r => router.delete(route('academic-sessions.destroy', props.session.id), {
                    data: { force: deleteMode.value === 'force' },
                    preserveScroll: true,
                }))

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: deleteMode.value === 'force'
                        ? 'Session permanently deleted'
                        : 'Session moved to trash',
                    life: 4000
                })

                modal.emitter.value?.emit('deleted');

                // Resolve with success info
                modal.closeCurrent()

            } catch (err: any) {
                errorMessage.value = err.message || 'Failed to delete session'
                toast.add({
                    severity: 'error',
                    summary: 'Deletion Failed',
                    detail: errorMessage.value,
                    life: 7000
                })
            } finally {
                isSubmitting.value = false
            }
        }
    })
}

const closeModal = () => {
    modal.closeCurrent()
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="text-center">
            <h3 class="text-xl font-bold text-red-700 dark:text-red-400 mb-2">
                Delete Academic Session
            </h3>
            <div class="text-lg font-semibold text-surface-900 dark:text-surface-50">
                {{ session.name }}
            </div>
            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                {{ dateRange }}
            </div>
        </div>

        <!-- Critical Warnings -->
        <Message severity="error" :closable="false" class="border-l-4 border-red-600 bg-red-50 dark:bg-red-950/40">
            <div class="space-y-3 text-sm">
                <p class="font-medium">Warning — this action affects historical records</p>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li>Soft delete → can be restored later</li>
                    <li v-if="canPermanentDelete">Permanent delete → irreversible removal of all data</li>
                    <li>Current active sessions cannot be deleted</li>
                    <li>Sessions with terms may require cleanup first</li>
                </ul>
            </div>
        </Message>

        <!-- Delete Mode Selection -->
        <div v-if="canPermanentDelete" class="space-y-3">
            <label class="font-medium block">Delete Type</label>
            <div class="flex flex-col sm:flex-row gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="deleteMode" value="soft" class="form-radio" />
                    <span>Soft Delete (movable to trash)</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="deleteMode" value="force" class="form-radio" />
                    <span class="text-red-600 dark:text-red-400 font-medium">
                        Permanent Delete
                    </span>
                </label>
            </div>
        </div>

        <!-- Confirmation phrase for permanent delete -->
        <div v-if="deleteMode === 'force'" class="field">
            <label for="confirm-delete" class="block font-medium mb-2 text-surface-900 dark:text-surface-100">
                Type <strong>"delete permanently"</strong> to confirm
            </label>
            <InputText id="confirm-delete" v-model="confirmationText" class="w-full" placeholder="delete permanently"
                :class="{ 'p-invalid': confirmationText && !isConfirmed }" />
        </div>

        <!-- Error -->
        <Message v-if="errorMessage" severity="error" :closable="false">
            {{ errorMessage }}
        </Message>

        <!-- Action Buttons -->
        <div
            class="flex flex-col sm:flex-row gap-3 justify-end pt-4 border-t border-surface-200 dark:border-surface-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text :disabled="isSubmitting"
                @click="closeModal" />

            <Button v-if="canSoftDelete || canPermanentDelete"
                :label="deleteMode === 'force' ? 'Permanently Delete' : 'Delete Session'"
                :icon="deleteMode === 'force' ? 'pi pi-trash' : 'pi pi-trash'" severity="danger" :loading="isSubmitting"
                :disabled="isSubmitting || (deleteMode === 'force' && !isConfirmed)" @click="performDelete" />

            <div v-else class="text-sm font-medium text-red-600 dark:text-red-400 self-center">
                This session cannot be deleted at this time
            </div>
        </div>
    </div>
</template>

<style scoped>
.form-radio {
    @apply h-4 w-4 text-danger-600 border-gray-300 focus:ring-danger-500;
}
</style>
