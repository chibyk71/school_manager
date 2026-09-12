<!--
resources/js/Components/Forms/SessionForm.vue
=================================================================

Reusable form component for creating and editing Academic Sessions.

Phase 2: no is_current checkbox. Lifecycle uses explicit plan/activate/pause/resume/close/reopen.
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    InputText,
    Calendar,
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

const minEndDate = computed(() => {
    if (!form.value.start_date) return undefined
    return new Date(form.value.start_date)
})

const maxStartDate = computed(() => {
    if (!form.value.end_date) return addYears(new Date(), 10)
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

const formatDate = (date: string | Date | null) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split('T')[0];
};

const startDateComputed = computed({
    get: () => form.value.start_date ? new Date(form.value.start_date) : null,
    set: (val) => { form.value.start_date = formatDate(val); }
});

const endDateComputed = computed({
    get: () => form.value.end_date ? new Date(form.value.end_date) : null,
    set: (val) => { form.value.end_date = formatDate(val); }
});

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
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

        <!-- Phase 2: lifecycle via plan/activate/pause/resume/close/reopen — no is_current -->

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
