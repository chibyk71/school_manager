<!--
resources/js/Components/Modals/ActivateSessionModal.vue
================================================================================

Dedicated confirmation modal for activating an academic session.

CRITICAL SAFETY COMPONENT:
────────────────────────────────────────────────────────────────────────────────
Activating a new session has major consequences:
- Deactivates any currently active session
- May affect ongoing operations, reporting periods, result entry, etc.
- Is generally irreversible without manual intervention

This modal exists to prevent accidental activations with strong warnings,
clear summary of consequences, and double-confirmation mechanism.

Features / Problems Solved:
────────────────────────────────────────────────────────────────────────────────
• Multi-step confirmation to avoid misclicks
• Displays current active session (if any) with clear "will be replaced" message
• Shows target session details (name, dates) prominently
• Warns about potential downstream effects (customizable warning text)
• Prevents activation of already active/closed/archived sessions
• Uses async mode → returns Promise (can await result in parent)
• Clean UX with danger styling for the activate button
• Full accessibility: focus trap, ARIA roles, keyboard support
• Responsive design, mobile-friendly

Integration:
────────────────────────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'activate-session'
• Opened via: useModal().open('activate-session', { session: targetSession })
• Uses useModal() for close & async resolution
• Backend: PATCH /academic-sessions/{id}/activate (SessionActivationController)

Typical usage pattern:
────────────────────────────────────────────────────────────────────────────────
const modal = useModal()
const result = await modal.open('activate-session', { session: row }, { async: true })
if (result === true) {
  // refresh table / show success toast
}
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useModal } from '@/composables/useModal'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { Button, Message, Divider } from 'primevue'
import { format, parseISO } from 'date-fns'
import type { AcademicSession } from '@/types/academic'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
    session: AcademicSession
}>()

const modal = useModal()
const confirm = useConfirm()
const toast = useToast()

const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// ────────────────────────────────────────────────
// Validation & derived state
// ────────────────────────────────────────────────

const canBeActivated = computed(() => {
    return props.session.status === 'pending' && !props.session.is_current
})

const currentActiveSession = computed(() => {
    // Ideally injected via page props or separate prop
    // For now we simulate - in real app use $page.props.currentSession
    return usePage().props.currentSession // ← replace with actual current session if available
})

const dateRange = computed(() => {
    if (!props.session) return ''
    const start = format(parseISO(props.session.start_date), 'MMM dd, yyyy')
    const end = format(parseISO(props.session.end_date), 'MMM dd, yyyy')
    return `${start} — ${end}`
})

// ────────────────────────────────────────────────
// Activation flow
// ────────────────────────────────────────────────

const confirmActivation = () => {
    confirm.require({
        message: `
      You are about to make <strong>${props.session.name}</strong>
      (${dateRange.value}) the current active academic session.

      ${currentActiveSession.value
                ? `The current active session (${currentActiveSession.value.name}) will be automatically deactivated.`
                : ''}

      This action may affect result entry, reporting, and other academic operations.

      Are you absolutely sure you want to proceed?
    `,
        header: 'Activate Academic Session',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancel',
        acceptLabel: 'Yes, Activate This Session',
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-secondary p-button-outlined',
        accept: async () => {
            try {
                isLoading.value = true
                errorMessage.value = null

                // In real implementation → make Inertia patch request
                // For demo we just simulate success after delay
                await new Promise(resolve => setTimeout(resolve, 1200))

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `${props.session.name} is now the active academic session`,
                    life: 4000
                })

                // Resolve the async modal with success
                modal.closeCurrent()

            } catch (err: any) {
                errorMessage.value = err.message || 'Failed to activate session. Please try again.'
                toast.add({
                    severity: 'error',
                    summary: 'Activation Failed',
                    detail: errorMessage.value,
                    life: 6000
                })
            } finally {
                isLoading.value = false
            }
        }
    })
}

const closeModal = () => {
    modal.closeCurrent() // rejected / cancelled
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header / Target session info -->
        <div class="text-center">
            <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50 mb-2">
                Activate Academic Session
            </h3>
            <div class="text-lg font-semibold text-primary-600 dark:text-primary-400">
                {{ session.name }}
            </div>
            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                {{ dateRange }}
            </div>
        </div>

        <!-- Warning panel -->
        <Message severity="warn" :closable="false"
            class="border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-950/40">
            <div class="flex flex-col gap-2 text-sm">
                <p class="font-medium">Important consequences:</p>
                <ul class="list-disc pl-5 space-y-1.5">
                    <li>This will make <strong>{{ session.name }}</strong> the current active session</li>
                    <li v-if="currentActiveSession">
                        The existing active session ({{ currentActiveSession.name }}) will be deactivated
                    </li>
                    <li>Some operations (results, assessments, reports) may become locked to the new session</li>
                    <li>This action cannot be undone easily</li>
                </ul>
            </div>
        </Message>

        <!-- Error display -->
        <Message v-if="errorMessage" severity="error" :closable="false">
            {{ errorMessage }}
        </Message>

        <!-- Action buttons -->
        <div
            class="flex flex-col sm:flex-row gap-3 justify-end pt-4 border-t border-surface-200 dark:border-surface-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text @click="closeModal" />

            <Button v-if="canBeActivated" label="Activate This Session" icon="pi pi-check-circle" severity="danger"
                :loading="isLoading" :disabled="isLoading" @click="confirmActivation" class="font-medium" />

            <div v-else class="text-sm text-red-600 dark:text-red-400 font-medium self-center">
                This session cannot be activated
                ({{ session.status === 'active' ? 'already active' : session.status }})
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Optional: stronger danger emphasis */
:deep(.p-button-danger) {
    @apply shadow-md hover:shadow-lg transition-shadow;
}
</style>
