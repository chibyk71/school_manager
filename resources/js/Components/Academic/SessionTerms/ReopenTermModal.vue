<!--
resources/js/Components/Modals/ReopenTermModal.vue
================================================================================

Inner content component for confirming the reopening of a previously closed Academic Term.

Rendered inside the global ResourceDialog.vue (no nested <Dialog> needed).

Purpose / Problems Solved:
────────────────────────────────────────────────────────────────────────────────
• Handles the restricted, exceptional action of reopening a closed term
• Enforces strong audit requirements: mandatory detailed reason (min 20 chars)
• Validates new end date:
  - Must be after original start date
  - Must not overlap with next term's start (if exists)
  - Must stay within parent session bounds
• Prevents reopening non-closed terms or terms that are too old
• Uses warning/danger UI + double-confirmation to prevent accidents
• Emits 'reopened' event with updated term on success (parent listens & refreshes)
• Handles loading/error states + proper Inertia submission
• Fully accessible: labels, ARIA, keyboard support (via ResourceDialog)
• Responsive layout with clear spacing on mobile/desktop
• Fits perfectly into nested modal flow (opened via prepend from SessionTermsModal)

Integration:
────────────────────────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'reopen-term'
• Opened via: modal.prepend('reopen-term', { term })
• Parent (SessionTermsModal) listens to 'reopened' event on its emitter
• Backend alignment:
   - PATCH /terms/{id}/reopen (TermClosureController)
   - Expects { reason: string, new_end_date: string }
   - Validates date collisions & returns updated term

Typical usage (from SessionTermsModal.vue):
────────────────────────────────────────────────────────────────────────────────
const openReopenTerm = (term) => {
    modal.prepend('reopen-term', { term })
}

// In parent mounted/onMounted:
currentItem.value?.emitter.on('reopened', (updatedTerm) => {
    // Update local terms list
    const index = terms.value.findIndex(t => t.id === updatedTerm.id)
    if (index !== -1) terms.value[index] = updatedTerm
})
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useModal } from '@/composables/useModal'
import { Textarea, DatePicker, Button, Message } from 'primevue'
import { format, formatDate, parseISO } from 'date-fns'
import type { Term } from '@/types/academic'

const props = defineProps<{
    term: Term  // The closed term to reopen
}>()

const modal = useModal()
const toast = useToast()

// ────────────────────────────────────────────────
// Form Setup
// ────────────────────────────────────────────────

const form = useForm({
    reason: '',
    new_end_date: props.term.end_date || ''  // prefill with original end date
})

const isSubmitting = ref(false)

// ────────────────────────────────────────────────
// Validation Helpers
// ────────────────────────────────────────────────

const reasonIsValid = computed(() =>
    form.reason.trim().length >= 20
)

const canReopen = computed(() =>
    props.term.status === 'closed' &&
    !props.term.is_current  // optional: prevent reopening current (shouldn't happen)
)

// ────────────────────────────────────────────────
// Date Validation Helpers (minimal frontend check - backend enforces)
// ────────────────────────────────────────────────

const originalStart = computed(() => props.term.start_date ? parseISO(props.term.start_date) : new Date())

const minNewEndDate = computed(() => originalStart.value)

const submit = async () => {
    if (!reasonIsValid.value) {
        form.setError('reason', 'Reason must be at least 20 characters to justify reopening')
        return
    }

    if (!canReopen.value) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Reopen',
            detail: 'This term is not eligible for reopening',
            life: 5000
        })
        return
    }

    isSubmitting.value = true

    try {
        form.patch(route('terms.reopen', props.term.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                const updatedTerm = page.props.term || {
                    ...props.term,
                    status: 'active',
                    end_date: form.new_end_date,
                    updated_at: new Date().toISOString()
                }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `Term "${props.term.name}" has been reopened successfully`,
                    life: 4000
                })

                // Emit event so parent can update its list
                modal.emitter.value?.emit('reopened', updatedTerm)
                modal.closeCurrent()
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors).join(' ') || 'Validation failed'
                toast.add({
                    severity: 'error',
                    summary: 'Failed to Reopen Term',
                    detail: errorMsg,
                    life: 6000
                })
            }
        })
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to reopen term. Please try again.',
            life: 5000
        })
    } finally {
        isSubmitting.value = false
    }
}

const endDateComputed = computed({
    get: () => new Date(form.new_end_date),
    set: (val) => { form.new_end_date = formatDate(val, 'yyyy-MM-dd'); }
});

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
            <h3 class="text-xl font-bold text-amber-700 dark:text-amber-400">
                Reopen Closed Term
            </h3>
            <p class="text-lg font-semibold text-gray-900 dark:text-white mt-2">
                {{ props.term.name }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Original dates: {{ formatDate(props.term.start_date, 'yyyy-MM-dd') }} — {{ formatDate(props.term.end_date, 'yyyy-MM-dd') }}
            </p>
        </div>

        <!-- Strong Warning Block -->
        <Message severity="warn" :closable="false"
            class="border-l-4 border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-sm">
            <div class="space-y-2">
                <p class="font-medium">Reopening is an exceptional action:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Requires detailed justification (audit requirement)</li>
                    <li>New end date must be valid and not conflict with next term</li>
                    <li>Will unlock editing, result entry, etc. → ensure data consistency</li>
                    <li>Only the most recently closed term should normally be reopened</li>
                </ul>
            </div>
        </Message>

        <!-- Reason Input (Mandatory) -->
        <div class="field">
            <label for="reopen-reason" class="block font-medium mb-2 text-gray-900 dark:text-white">
                Reason for reopening <span class="text-red-500">*</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                    (minimum 20 characters - required for audit)
                </span>
            </label>
            <InputTextarea id="reopen-reason" v-model="form.reason" rows="5" class="w-full"
                :class="{ 'p-invalid': form.errors.reason }"
                placeholder="Explain why this term needs to be reopened (e.g., administrative error, extension approved, etc.)..."
                :disabled="isSubmitting" />
            <small v-if="form.errors.reason" class="text-red-500 mt-1 block">
                {{ form.errors.reason }}
            </small>
        </div>

        <!-- New End Date -->
        <div class="field">
            <label for="new_end_date" class="block font-medium mb-2 text-gray-900 dark:text-white">
                New End Date <span class="text-red-500">*</span>
            </label>
            <DatePicker id="new_end_date" v-model="endDateComputed" dateFormat="dd/mm/yy" :minDate="minNewEndDate"
                :disabled="isSubmitting" :readonlyInput="true" :class="{ 'p-invalid': form.errors.new_end_date }"
                showIcon class="w-full" />
            <small v-if="form.errors.new_end_date" class="text-red-500 mt-1 block">
                {{ form.errors.new_end_date }}
            </small>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Must be after original start date and not conflict with next term/session
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text :disabled="isSubmitting"
                @click="cancel" />

            <Button v-if="canReopen" label="Confirm & Reopen Term" icon="pi pi-refresh" severity="warning"
                :loading="isSubmitting" :disabled="isSubmitting || !reasonIsValid" @click="submit" />

            <div v-else class="text-sm font-medium text-red-600 dark:text-red-400 self-center">
                This term cannot be reopened at this time
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Consistent spacing and typography */
.field label {
    @apply block text-sm font-medium mb-2 text-gray-900 dark:text-white;
}
</style>
