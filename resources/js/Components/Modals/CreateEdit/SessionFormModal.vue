<!--
resources/js/Components/Modals/CreateEdit/SessionFormModal.vue
=================================================================

Main modal for creating and editing academic sessions.

Features / Problems Solved:
───────────────────────────────────────────────────────────────
• Unified form for both create and edit operations (mode-based)
• Full validation feedback from Laravel (via Inertia errors)
• Date range validation (start ≤ end)
• Prevents changing name/start_date after activation (read-only mode)
• Shows current active session warning when editing
• Uses your existing ModalDirectory registration system
• Reuses SessionForm.vue for form fields (DRY)
• Handles async submission with loading state & toast feedback
• Proper accessibility: focus trap, ARIA labels, keyboard navigation
• Responsive: works well on mobile & desktop (PrimeVue Dialog + Tailwind)

Integration Points:
───────────────────────────────────────────────────────────────
• Registered in ModalDirectory.ts as 'session-form'
• Opened via useModal().open('session-form', { session?, mode: 'create'|'edit' })
• Uses useModalForm composable for Inertia form handling
• Emits 'saved' event on success → parent can refresh table
• Backend: POST /academic-sessions (store) or PATCH /academic-sessions/{id} (update)

Dependencies:
• PrimeVue: Dialog, InputText, DatePicker, Button, Toast
• Composables: useModal, useModalForm, usePermissions
• Reusable: SessionForm.vue
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useModalForm } from '@/composables/useModalForm'
import { useModal } from '@/composables/useModal'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { usePermissions } from '@/composables/usePermissions'
import { usePage } from '@inertiajs/vue3'
import type { AcademicSession, AcademicSessionFormData } from '@/types/academic'
import SessionForm from '@/Components/Academic/Session/SessionForm.vue'

const props = defineProps<{
    session?: AcademicSession       // Passed when editing
    mode: 'create' | 'edit'         // Determines form behavior
}>()

const emit = defineEmits<{
    (e: 'saved'): void
}>()

const modal = useModal()
const toast = useToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const page = usePage();
// ────────────────────────────────────────────────
// Form Setup
// ────────────────────────────────────────────────

const initialData = computed<AcademicSessionFormData>(() => props.session || {
    name: '',
    start_date: null,
    end_date: null,
    is_current: false,
})

const { form, submit, isLoading, errors } = useModalForm(initialData.value, {
    resource: 'academic-sessions',
    resourceId: props.session?.id,
    method: props.mode === 'create' ? 'post' : 'patch',
    successMessage: props.mode === 'create'
        ? 'Academic session created successfully'
        : 'Academic session updated successfully',
    onSuccess: () => {
        emit('saved')
        modal.closeCurrent()
    },
})

// ────────────────────────────────────────────────
// Read-only fields after activation
// ────────────────────────────────────────────────

const isReadOnly = computed(() =>
    props.mode === 'edit' &&
    props.session?.status !== 'pending'  // or !is_current, depending on your logic
)

const canEditDates = computed(() =>
    hasPermission('academic-sessions.edit') && !isReadOnly.value
)

// ────────────────────────────────────────────────
// Prevent accidental current session override
// ────────────────────────────────────────────────

watch(() => form.is_current, (newVal) => {
    if (newVal && page.props.currentSession) {
        confirm.require({
            message: `There is already an active session (${page.props.currentSession.name}). Activating this one will deactivate the current session. Continue?`,
            header: 'Confirm Activation',
            icon: 'pi pi-exclamation-triangle',
            accept: () => { /* proceed */ },
            reject: () => {
                form.is_current = false
            }
        })
    }
})

// ────────────────────────────────────────────────
// Close handler
// ────────────────────────────────────────────────

const closeModal = () => {
    if (form.isDirty) {
        confirm.require({
            message: 'You have unsaved changes. Are you sure you want to close?',
            header: 'Unsaved Changes',
            icon: 'pi pi-info-circle',
            accept: modal.closeCurrent,
            reject: () => { /* stay open */ }
        })
    } else {
        modal.closeCurrent()
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Warning banner if editing activated session -->
        <div v-if="isReadOnly" class="bg-yellow-50 dark:bg-yellow-950 border-l-4 border-yellow-400 p-4 rounded">
            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                This session is already active or closed. Only certain fields can be edited.
            </p>
        </div>

        <!-- Main form -->
        <SessionForm v-model="form" :errors="errors" :disabled="!hasPermission('academic-sessions.edit')"
            :readOnly="isReadOnly" :canEditDates="canEditDates" />

        <!-- Form actions -->
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-surface-200 dark:border-surface-700">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" text @click="closeModal" />
            <Button :label="mode === 'create' ? 'Create Session' : 'Update Session'" icon="pi pi-save"
                :loading="isLoading" :disabled="!form.isDirty || form.processing" @click="submit" />
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Optional custom styling if needed beyond PrimeVue defaults */
:deep(.p-dialog .p-dialog-content) {
    @apply p-6;
}
</style>
