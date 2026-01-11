<!--
resources/js/Components/Forms/SessionForm.vue
=================================================================

Reusable form component for creating and editing Academic Sessions.

Features / Problems Solved:
───────────────────────────────────────────────────────────────
• Unified form fields used both in full-page views and modals
• Full support for PrimeVue form components + validation display
• Date range enforcement (end_date ≥ start_date)
• Conditional read-only mode (important after activation)
• Permission-aware field disabling
• Responsive layout (good on mobile & desktop)
• Clear visual feedback for errors (red borders + messages)
• Accessibility: proper labels, ARIA attributes, keyboard navigation
• Clean separation of presentation & logic (easy to reuse/extend)

Usage patterns:
───────────────────────────────────────────────────────────────
1. In modals:     <SessionForm v-model="form" :errors="form.errors" ... />
2. In full pages: <SessionForm v-model="sessionForm.data" :errors="sessionForm.errors" />

Integration:
• Used inside SessionFormModal.vue (primary consumer)
• Can be used in future full-page create/edit views
• Works with useModalForm / useForm (v-model + errors prop)

Backend alignment:
• Fields match AcademicSession model & Store/Update requests
• Date fields expect ISO strings ("YYYY-MM-DD")
• is_current is boolean (checkbox)

Props / v-model structure:
  v-model → two-way binding on entire form object
  errors  → record<string, string> from Inertia/Laravel
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    InputText,
    Calendar,
    Checkbox,
    Message,
    Tooltip,
    type DatePicker,
} from 'primevue'
import { usePermissions } from '@/composables/usePermissions'
import type { AcademicSessionFormData } from '@/types/academic'
import { addYears, isAfter, isBefore } from 'date-fns'

const form = defineModel<AcademicSessionFormData>({
    required: true
})

const props = withDefaults(defineProps<{
    errors: Record<string, string>
    disabled?: boolean
    readOnly?: boolean
    canEditDates?: boolean
}>(), {
    disabled: false,
    readOnly: false,
    canEditDates: true,
})

const { hasPermission } = usePermissions()

// ────────────────────────────────────────────────
// Date range validation & auto-adjustment
// ────────────────────────────────────────────────

const minEndDate = computed(() => {
    if (!form.value.start_date) return undefined
    return new Date(form.value.start_date)
})

const maxStartDate = computed(() => {
    if (!form.value.end_date) return addYears(new Date(), 10) // reasonable future limit
    return new Date(form.value.end_date)
})

watch(() => form.value.start_date, (newStart) => {
    if (newStart && form.value.end_date && isAfter(new Date(newStart), new Date(form.value.end_date))) {
        form.value.end_date = newStart
    }
})

watch(() => form.value.end_date, (newEnd) => {
    if (newEnd && form.value.start_date && isBefore(new Date(newEnd), new Date(form.value.start_date))) {
        form.value.start_date = newEnd
    }
})

// Helper to format Date to 'YYYY-MM-DD'
const formatDate = (date: string | Date | null) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split('T')[0]; // Returns "2026-01-10"
};

const startDateComputed = computed({
    get: () => form.value.start_date ? new Date(form.value.start_date) : null,
    set: (val) => { form.value.start_date = formatDate(val); }
});

const endDateComputed = computed({
    get: () => form.value.end_date ? new Date(form.value.end_date) : null,
    set: (val) => { form.value.end_date = formatDate(val); }
});

// ────────────────────────────────────────────────
// Computed flags for UI control
// ────────────────────────────────────────────────

const isDisabled = computed(() =>
    props.disabled || !hasPermission('academic-sessions.edit') || props.readOnly
)

const datesAreReadOnly = computed(() =>
    props.readOnly || !props.canEditDates || isDisabled.value
)

const nameIsReadOnly = computed(() =>
    props.readOnly || isDisabled.value
)
</script>

<template>
    <div class="space-y-6">
        <!-- Session Name -->
        <div class="field">
            <label for="name" class="font-medium mb-1 block">
                Session Name <span class="text-red-500">*</span>
            </label>
            <InputText id="name" v-model="form.name" :disabled="nameIsReadOnly" :readonly="nameIsReadOnly"
                :invalid="!!errors.name" placeholder="e.g. 2025/2026" class="w-full" autocomplete="off" />
            <small v-if="errors.name" class="text-red-500 mt-1 block">
                {{ errors.name }}
            </small>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Start Date -->
            <div class="field">
                <label for="start_date" class="font-medium mb-1 block">
                    Start Date <span class="text-red-500">*</span>
                </label>
                <DatePicker id="start_date" v-model="startDateComputed" dateFormat="yy-mm-dd"
                    :minDate="new Date('2020-01-01')" :maxDate="maxStartDate" :disabled="datesAreReadOnly"
                    :readonlyInput="datesAreReadOnly" :invalid="!!errors.start_date" showIcon iconDisplay="input"
                    class="w-full" />
                <small v-if="errors.start_date" class="text-red-500 mt-1 block">
                    {{ errors.start_date }}
                </small>
            </div>

            <!-- End Date -->
            <div class="field">
                <label for="end_date" class="font-medium mb-1 block">
                    End Date <span class="text-red-500">*</span>
                </label>
                <DatePicker id="end_date" v-model="endDateComputed" dateFormat="yy-mm-dd" :minDate="minEndDate"
                    :disabled="datesAreReadOnly" :readonlyInput="datesAreReadOnly" :invalid="!!errors.end_date" showIcon
                    iconDisplay="input" class="w-full" />
                <small v-if="errors.end_date" class="text-red-500 mt-1 block">
                    {{ errors.end_date }}
                </small>
            </div>
        </div>

        <!-- Set as Current Session -->
        <div class="field flex items-center gap-2">
            <Checkbox id="is_current" v-model="form.is_current" :binary="true" :disabled="isDisabled" />
            <label for="is_current" class="cursor-pointer select-none">
                Mark this session as the current/active session
            </label>
        </div>

        <!-- Warning when trying to activate while another is active -->
        <Message v-if="form.is_current && !props.readOnly" severity="warn" :closable="false" class="text-sm">
            Activating this session will automatically deactivate any currently active session.
        </Message>

        <!-- Read-only notice -->
        <Message v-if="props.readOnly" severity="info" :closable="false" class="text-sm">
            This session is already active or closed. Some fields are protected from modification.
        </Message>
    </div>
</template>

<style scoped lang="postcss">
.field label {
    @apply text-sm text-gray-700 dark:text-gray-300;
}

:deep(.p-inputtext),
:deep(.p-calendar) {
    @apply w-full;
}

:deep(.p-invalid) {
    @apply border-red-400;
}
</style>
