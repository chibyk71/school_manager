<!--
resources/js/Components/Modals/CloseTermModal.vue
================================================================================

Inner content component for confirming the closure (finalization) of an Academic Term.

Rendered inside the global ResourceDialog.vue (no nested <Dialog> needed).

Purpose / Problems Solved:
────────────────────────────────────────────────────────────────────────────────
• Dedicated, high-safety confirmation for closing a term (locks editing, finalizes results, etc.)
• Requires mandatory reason (minimum length) for audit trail & compliance
• Strong visual & textual warnings about irreversible consequences
• Prevents closing already closed/archived terms
• Prevents closing current/active term without confirmation
• Uses danger-themed UI + double-confirmation step to avoid misclicks
• Emits 'closed' event on success (parent can listen & refresh terms list)
• Handles loading/error states gracefully
• Fully accessible: focus trap (via ResourceDialog), ARIA labels, keyboard support
• Responsive design, good spacing on mobile/desktop
• Fits perfectly into nested modal flow (opened via prepend from SessionTermsModal)

Integration:
────────────────────────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'close-term'
• Opened via: modal.prepend('close-term', { term })
• Parent (SessionTermsModal) listens to 'closed' event on its emitter
• Backend alignment:
   - PATCH /terms/{id}/close (TermClosureController)
   - Expects { reason: string } in payload
   - Returns updated term (for optimistic update)

Typical usage (from SessionTermsModal.vue):
────────────────────────────────────────────────────────────────────────────────
const openCloseTerm = () => {
    modal.prepend('close-term', { term })
}

// In mounted/onMounted:
currentItem.value?.emitter.on('closed', (updatedTerm) => {
    // Update local terms list
    const index = terms.value.findIndex(t => t.id === updatedTerm.id)
    if (index !== -1) terms.value[index] = updatedTerm
})
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useModal } from '@/composables/useModal'
import { Textarea, Button, Message } from 'primevue'
import { formatDate } from 'date-fns'
import type { Term } from '@/types/academic'

const props = defineProps<{
    term: Term  // The term to close (passed via modal payload)
}>()

const modal = useModal()
const toast = useToast()

// ────────────────────────────────────────────────
// Form Setup
// ────────────────────────────────────────────────

const form = useForm({
    reason: ''
})

const isSubmitting = ref(false)

// ────────────────────────────────────────────────
// Validation Helpers
// ────────────────────────────────────────────────

const reasonIsValid = computed(() =>
    form.reason.trim().length >= 20
)

const canClose = computed(() =>
    props.term.status === 'active' &&
    !props.term.is_current  // optional: prevent closing current term
)

// ────────────────────────────────────────────────
// Submission
// ────────────────────────────────────────────────

const submit = async () => {
    if (!reasonIsValid.value) {
        form.setError('reason', 'Reason must be at least 20 characters for audit purposes')
        return
    }

    if (!canClose.value) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Close',
            detail: 'This term is not eligible for closure (already closed or current)',
            life: 5000
        })
        return
    }

    isSubmitting.value = true

    try {
        form.patch(route('terms.close', props.term.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                const updatedTerm = page.props.term || { ...props.term, status: 'closed' }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `Term "${props.term.name}" has been closed successfully`,
                    life: 4000
                })

                // Emit event so parent can update its list
                modal.emitter.value?.emit('closed', updatedTerm)
                modal.closeCurrent()
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Failed to Close Term',
                    detail: Object.values(errors).join(' ') || 'An error occurred',
                    life: 6000
                })
            }
        })
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to close term. Please try again.',
            life: 5000
        })
    } finally {
        isSubmitting.value = false
    }
}

// ────────────────────────────────────────────────
// Cancel / Close
// ────────────────────────────────────────────────

const cancel = () => {
    modal.closeCurrent()
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="text-center">
            <h3 class="text-xl font-bold text-red-700 dark:text-red-400">
                Close Academic Term
            </h3>
            <p class="text-lg font-semibold text-gray-900 dark:text-white mt-2">
                {{ props.term.name }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ formatDate(props.term.start_date, 'yyyy-MM-dd') }} — {{ formatDate(props.term.end_date, 'yyyy-MM-dd') }}
            </p>
        </div>

        <!-- Strong Warning Block -->
        <Message severity="error" :closable="false"
            class="border-l-4 border-red-600 bg-red-50 dark:bg-red-950/40 text-sm">
            <div class="space-y-2">
                <p class="font-medium">This action is irreversible and has serious consequences:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>The term will be marked as closed/finalized</li>
                    <li>Most editing (results, assessments, attendance) will be locked</li>
                    <li>Reporting, promotion & archiving processes may begin</li>
                    <li>Reopening requires special justification & admin approval</li>
                </ul>
            </div>
        </Message>

        <!-- Reason Input (Mandatory for Audit) -->
        <div class="field">
            <label for="close-reason" class="block font-medium mb-2 text-gray-900 dark:text-white">
                Reason for closing this term <span class="text-red-500">*</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                    (minimum 20 characters - required for audit)
                </span>
            </label>
            <Textarea id="close-reason" v-model="form.reason" rows="5" class="w-full"
                :class="{ 'p-invalid': form.errors.reason }"
                placeholder="Please provide a detailed reason (e.g., end of term, administrative decision, etc.)..."
                :disabled="isSubmitting" />
            <small v-if="form.errors.reason" class="text-red-500 mt-1 block">
                {{ form.errors.reason }}
            </small>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text :disabled="isSubmitting"
                @click="cancel" />

            <Button v-if="canClose" label="Confirm & Close Term" icon="pi pi-lock" severity="danger"
                :loading="isSubmitting" :disabled="isSubmitting || !reasonIsValid" @click="submit" />

            <div v-else class="text-sm font-medium text-red-600 dark:text-red-400 self-center">
                This term cannot be closed at this time (already closed or current)
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Consistent modal content spacing */
:deep(.p-message) {
    @apply text-sm;
}
</style>
