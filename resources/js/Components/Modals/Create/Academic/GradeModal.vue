<!--
GradeModal.vue
───────────────────────────────────────────────────────────────────────────────────────────────
Purpose / Features Implemented:
• Single modal for BOTH create and edit of Grade records
• Handles form submission via Inertia (POST/PUT) with full validation error mapping
• Supports many-to-many school section assignment via PrimeVue MultiSelect
• Displays computed score range preview in real-time
• Uses useModalForm composable for consistent form handling (processing, errors, reset)
• Fully responsive, accessible (ARIA labels, keyboard navigation), Tailwind + PrimeVue styled
• Integrates with ModalService (title, size, persistent behavior from ModalDirectory)
• Matches backend GradeFormData shape exactly

How it fits into the Grades Module:
───────────────────────────────────────────────────────────────────────────────────────────────
• Opened via useModal().open('grade-form', { grade?: Grade | null })
• Used from Grades.vue (new button) and edit actions in DataTable
• Receives grade prop (null = create, existing = edit)
• Submits to /grades (POST) or /grades/{id} (PUT) via Inertia
• On success: emits 'saved' event → parent can refresh DataTable / close modal
• Uses types from resources/js/Types/grade.ts for full type safety

Tech Stack Alignment:
• Vue 3 Composition API + <script setup>
• PrimeVue: InputText, InputNumber, Textarea, MultiSelect, Button, Divider
• Inertia.js: useForm + router for submission
• Tailwind CSS + custom classes for layout/responsiveness
• useModalForm composable (from your stack) for form state/errors/processing

Props:
• grade: Grade | null   → existing grade for edit, null for create

Emits:
• saved → after successful save (parent can refresh table)
• close → manual close request
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useModalForm } from '@/composables/useModalForm' // your custom composable
import type { Grade, GradeFormData } from '@/types/grade';
import AsyncSelect from '@/Components/forms/AsyncSelect.vue';

// ─── Props ────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    grade?: Grade | null
}>()

// ─── Emits ────────────────────────────────────────────────────────────────────────
const emit = defineEmits<{
    (e: 'saved'): void
    (e: 'close'): void
}>()

// ─── Toast Service ────────────────────────────────────────────────────────────────
const toast = useToast()

// ─── Form Setup using your useModalForm composable ────────────────────────────────
const {
    form,
    submit,
    isLoading,
    errors,
    hasErrors,
    clearErrors,
    reset
} = useModalForm<GradeFormData>({
    name: props.grade?.name || '',
    code: props.grade?.code || '',
    min_score: props.grade?.min_score || 0,
    max_score: props.grade?.max_score || 100,
    remark: props.grade?.remark || null,
    school_section_ids: props.grade?.school_sections?.map(s => s.id) || [],
}, {
    resource: 'grades',
    resourceId: props.grade?.id,
    successMessage: props.grade ? 'Grade updated successfully' : 'Grade created successfully',
    reload: ['grades'], // optional: reload DataTable data after save
    onSuccess: () => {
        emit('saved')
        emit('close')
    },
    onError: (err) => {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please correct the highlighted fields.',
            life: 5000
        })
    }
})

// ─── Computed: Real-time range preview ────────────────────────────────────────────
const scoreRangePreview = computed(() => {
    const min = form.min_score ?? 0
    const max = form.max_score ?? 100
    return min <= max ? `${min} – ${max}` : 'Invalid range'
})

// ─── Watch: Clear errors on input change ──────────────────────────────────────────
watch([() => form.name, () => form.code, () => form.min_score, () => form.max_score], () => {
    clearErrors()
})

// ─── Submit Handler ───────────────────────────────────────────────────────────────
const handleSubmit = () => {
    submit()
}

// ─── Close Handler ────────────────────────────────────────────────────────────────
const handleClose = () => {
    emit('close')
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header / Title -->
        <div class="flex items-center justify-between border-b pb-4">
            <h2 class="text-xl font-semibold text-gray-900">
                {{ props.grade ? 'Edit Grade' : 'Create New Grade' }}
            </h2>
            <button type="button" @click="handleClose" class="text-gray-400 hover:text-gray-600 focus:outline-none"
                aria-label="Close modal">
                <i class="pi pi-times text-xl"></i>
            </button>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Grade Name <span class="text-red-500">*</span>
                    </label>
                    <InputText id="name" v-model="form.name" class="mt-1 w-full" :class="{ 'p-invalid': errors.name }"
                        :disabled="isLoading" placeholder="e.g. Excellent, A, Distinction" required />
                    <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
                </div>

                <!-- Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">
                        Grade Code <span class="text-red-500">*</span>
                    </label>
                    <InputText id="code" v-model="form.code" class="mt-1 w-full" :class="{ 'p-invalid': errors.code }"
                        :disabled="isLoading" placeholder="e.g. A, B+, 7" required />
                    <small v-if="errors.code" class="p-error">{{ errors.code }}</small>
                </div>
            </div>

            <!-- Score Range -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="min_score" class="block text-sm font-medium text-gray-700">
                        Min Score <span class="text-red-500">*</span>
                    </label>
                    <InputNumber id="min_score" v-model="form.min_score" class="mt-1 w-full"
                        :class="{ 'p-invalid': errors.min_score }" :disabled="isLoading" :min="0" :max="100" showButtons
                        required />
                    <small v-if="errors.min_score" class="p-error">{{ errors.min_score }}</small>
                </div>

                <div>
                    <label for="max_score" class="block text-sm font-medium text-gray-700">
                        Max Score <span class="text-red-500">*</span>
                    </label>
                    <InputNumber id="max_score" v-model="form.max_score" class="mt-1 w-full"
                        :class="{ 'p-invalid': errors.max_score }" :disabled="isLoading" :min="form.min_score || 0"
                        :max="100" showButtons required />
                    <small v-if="errors.max_score" class="p-error">{{ errors.max_score }}</small>
                </div>

                <!-- Live Preview -->
                <div class="flex flex-col justify-end">
                    <label class="block text-sm font-medium text-gray-700">Range Preview</label>
                    <div class="mt-1 px-4 py-2 bg-gray-100 rounded-md text-center font-medium">
                        {{ scoreRangePreview }}
                    </div>
                </div>
            </div>

            <!-- Remark -->
            <div>
                <label for="remark" class="block text-sm font-medium text-gray-700">
                    Remark / Description (optional)
                </label>
                <Textarea id="remark" v-model="form.remark" rows="3" class="mt-1 w-full"
                    :class="{ 'p-invalid': errors.remark }" :disabled="isLoading"
                    placeholder="e.g. Outstanding performance, Excellent result..." />
                <small v-if="errors.remark" class="p-error">{{ errors.remark }}</small>
            </div>

            <!-- School Sections (Many-to-Many) -->
            <div>
                <label for="school_sections" class="block text-sm font-medium text-gray-700">
                    Assign to School Sections (optional – leave empty for school-wide)
                </label>
                <AsyncSelect :field="{ placeholder: 'Select sections...', search_url: route('school-section.options'), multiple: true, field_options: { option_label: 'name', option_value: 'id', search_delay: 300, search_key: 'q' } }"
                    id="school_sections" v-model="form.school_section_ids" class="mt-1 w-full"
                    :class="{ 'p-invalid': errors.school_section_ids }" :maxSelectedLabels="5" />
                <small v-if="errors.school_section_ids" class="p-error">{{ errors.school_section_ids }}</small>
                <p class="mt-1 text-sm text-gray-500">
                    This grade will apply to selected sections only. Leave empty for all sections.
                </p>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end gap-4 pt-6 border-t">
                <Button label="Cancel" icon="pi pi-times" severity="secondary" text @click="handleClose"
                    :disabled="isLoading" />
                <Button :label="props.grade ? 'Update Grade' : 'Create Grade'" icon="pi pi-check" :loading="isLoading"
                    type="submit" severity="success" />
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Optional: Custom Tailwind overrides for better spacing/alignment */
:deep(.p-multiselect) {
    @apply w-full;
}
</style>
