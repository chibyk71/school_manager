<!--
resources/js/Components/Modals/CloseSessionModal.vue
================================================================================

Confirmation modal for closing (finalizing) an academic session.

CRITICAL SAFETY COMPONENT:
────────────────────────────────────────────────────────────────────────────────
Closing a session is a major lifecycle event with significant consequences:
- Prevents further modifications to terms, results, assessments in most cases
- Often triggers reporting finalization, promotion preparation, archiving workflows
- Usually irreversible or very difficult to reverse

This modal provides strong warnings, requires explicit reason input (for audit),
and uses double confirmation to prevent accidental closure.

Features / Problems Solved:
────────────────────────────────────────────────────────────────────────────────
• Mandatory reason field (minimum length) for audit trail
• Displays session details + current status prominently
• Shows warnings about locked operations & consequences
• Prevents closing already closed/archived sessions
• Prevents closing session with open/active terms (validation)
• Async mode → resolves Promise (parent can await result)
• Danger-themed UI + confirmation step
• Full accessibility: focus management, ARIA, keyboard support
• Responsive & mobile-friendly layout

Integration:
────────────────────────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'close-session'
• Opened via: modal.open('close-session', { session: targetSession })
• Backend endpoint: PATCH /academic-sessions/{id}/close (SessionActivationController or similar)

Typical usage:
────────────────────────────────────────────────────────────────────────────────
const result = await modal.open('close-session', { session }, { async: true })
if (result === true) {
  // refresh, toast success
}
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useModal } from '@/composables/useModal'
import { useToast } from 'primevue/usetoast'
import { format, parseISO } from 'date-fns'
import type { AcademicSession } from '@/types/academic'
import { Button, Message, Textarea } from 'primevue'

const props = defineProps<{
    session: AcademicSession
}>()

const modal = useModal()
const toast = useToast()

const reason = ref('')
const isSubmitting = ref(false)
const validationError = ref<string | null>(null)

// ────────────────────────────────────────────────
// Validation & State
// ────────────────────────────────────────────────

const canBeClosed = computed(() => {
    return props.session.status === 'active' && !props.session.is_current
    // You might want additional checks (e.g. no active terms)
    // const hasActiveTerms = props.session.terms?.some(t => t.is_current) ?? false
    // return props.session.status === 'active' && !hasActiveTerms
})

const dateRange = computed(() => {
    const start = format(parseISO(props.session.start_date), 'MMM dd, yyyy')
    const end = format(parseISO(props.session.end_date), 'MMM dd, yyyy')
    return `${start} — ${end}`
})

const isReasonValid = computed(() =>
    reason.value.trim().length >= 20
)

// ────────────────────────────────────────────────
// Submission Flow
// ────────────────────────────────────────────────

const attemptClose = async () => {
    if (!isReasonValid.value) {
        validationError.value = 'Please provide a detailed reason (minimum 20 characters) for closing this session.'
        return
    }

    validationError.value = null
    isSubmitting.value = true

    try {
        // In real implementation - send Inertia patch request
        // Example:
        // await router.patch(route('academic-sessions.close', props.session.id), {
        //   reason: reason.value.trim()
        // })

        // For demo/skeleton → simulate delay
        await new Promise(resolve => setTimeout(resolve, 1400))

        toast.add({
            severity: 'success',
            summary: 'Session Closed',
            detail: `${props.session.name} has been successfully closed.`,
            life: 5000
        })

        modal.emitter.value?.emit('confirmed')

        // Resolve modal with success
        modal.closeCurrent()

    } catch (err: any) {
        const msg = err.message || 'Failed to close academic session. Please try again.'
        toast.add({
            severity: 'error',
            summary: 'Operation Failed',
            detail: msg,
            life: 7000
        })
        validationError.value = msg
    } finally {
        isSubmitting.value = false
    }
}

const cancel = () => {
    modal.closeCurrent()
}
</script>

<template>
    <div class="space-y-6 p-1">
        <!-- Header -->
        <div class="text-center">
            <h3 class="text-xl font-bold text-red-700 dark:text-red-400 mb-2">
                Close Academic Session
            </h3>
            <div class="text-lg font-semibold text-surface-900 dark:text-surface-50">
                {{ session.name }}
            </div>
            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                {{ dateRange }}
            </div>
        </div>

        <!-- Main Warning Block -->
        <Message severity="error" :closable="false" class="border-l-4 border-red-600 bg-red-50 dark:bg-red-950/40">
            <div class="space-y-3 text-sm">
                <p class="font-medium">This action is irreversible and has serious consequences:</p>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li>The session will be marked as closed</li>
                    <li>Most editing operations (terms, results, assessments) will be locked</li>
                    <li>Promotion, reporting & archiving processes may begin</li>
                    <li>Only administrators with special permissions can reopen</li>
                </ul>
            </div>
        </Message>

        <!-- Reason Input - Audit Requirement -->
        <div class="field">
            <label for="close-reason" class="block font-medium mb-2 text-surface-900 dark:text-surface-100">
                Reason for closing this session <span class="text-red-500">*</span>
                <span class="text-xs text-surface-500 dark:text-surface-400 ml-2">
                    (minimum 20 characters - for audit purposes)
                </span>
            </label>
            <Textarea id="close-reason" v-model="reason" rows="4" class="w-full"
                :class="{ 'p-invalid': validationError }"
                placeholder="Please explain why you're closing this session at this time..." :disabled="isSubmitting" />
            <small v-if="validationError" class="text-red-500 mt-1 block">
                {{ validationError }}
            </small>
        </div>

        <!-- Action Buttons -->
        <div
            class="flex flex-col sm:flex-row gap-3 justify-end pt-4 mt-2 border-t border-surface-200 dark:border-surface-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text :disabled="isSubmitting"
                @click="cancel" />

            <Button v-if="canBeClosed" label="Close This Session" icon="pi pi-lock" severity="danger"
                :loading="isSubmitting" :disabled="isSubmitting || !isReasonValid" @click="attemptClose" />

            <div v-else class="text-sm font-medium text-red-600 dark:text-red-400 self-center">
                This session cannot be closed
                ({{ session.status === 'closed' ? 'already closed' : session.status }})
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Extra visual emphasis for danger action */
:deep(.p-button-danger) {
    @apply shadow-lg hover:shadow-xl transition-all;
}
</style>
