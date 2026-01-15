<!--
  resources/js/components/forms/DynamicForm.vue

  PRODUCTION-READY – January 2026

  Main component for rendering dynamic/custom fields forms.

  Core purpose & responsibilities:
  • Loads custom fields for a given model (via useCustomFields composable)
  • Groups fields by category and renders them in collapsible or stacked sections
  • Manages form state using Inertia's useForm (two-way binding, validation errors)
  • Handles create/update submission with proper method detection
  • Displays loading, error, empty, and success states with toast feedback
  • Provides accessible form experience (ARIA roles, focus on first error, live regions)

  Features / Problems solved:
  • Unified rendering pipeline: category → CustomFieldRenderer → InputWrapper → PrimeVue input
  • Automatic merging of backend initial values + parent-provided initialData
  • Field-level errors shown inline (via InputWrapper) + form-level summary at top
  • Responsive layout: stacked on mobile, better spacing on larger screens
  • Accessibility: proper form role, aria-invalid propagation, focus trap on errors
  • Clean submission UX: loading state, success toast, cancel event
  • Error recovery: retry button on fields load failure

  Integration points:
  • Used in Create/Edit pages (e.g. StudentCreate.vue, StaffEdit.vue)
  • Used inside modals (e.g. CustomFieldModal preview, entity modals)
  • Can be used in form builder preview pane (with read-only mode later)
  • Expects submitUrl + optional method from parent component
  • Emits 'submitted' (success) and 'cancelled' events

  Dependencies:
  • useCustomFields composable (fields loading & initial values)
  • CustomFieldRenderer.vue (renders each individual field)
  • InputWrapper.vue (layout shell: label, hint, error, icons)
  • PrimeVue: Button, Message, ProgressSpinner, Toast
  • Inertia: useForm, router (for reload if needed)

  Usage example:
  <DynamicForm
    model="Student"
    :entity-id="student?.id"
    submit-url="/students"
    :initial-data="{ name: 'John' }"
    @submitted="onSuccess"
    @cancelled="onCancel"
  />
-->

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { ProgressSpinner, Message, Button } from 'primevue'
import CustomFieldRenderer from './CustomFieldRenderer.vue'
import { useCustomFields } from '@/composables/useCustomFields'

const props = defineProps<{
    model: string                      // e.g. 'Student', 'Staff', 'Guardian'
    entityId?: number | string         // present = edit mode
    submitUrl: string                  // Laravel route (e.g. '/api/students' or '/students/{id}')
    method?: 'post' | 'put' | 'patch'  // optional override
    initialData?: Record<string, any>  // extra values to merge
    title?: string                     // optional form heading
    readonly?: boolean                 // future: preview mode in builder
}>()

const emit = defineEmits<{
    (e: 'submitted'): void
    (e: 'cancelled'): void
}>()

const toast = useToast()

// ────────────────────────────────────────────────
// Load custom fields
// ────────────────────────────────────────────────
const {
    categories,
    initialValues,
    loading: fieldsLoading,
    error: fieldsError,
    refetch: reloadFields
} = useCustomFields({
    model: props.model,
    entityId: props.entityId,
    immediate: true
})

// ────────────────────────────────────────────────
// Inertia form setup
// ────────────────────────────────────────────────
const form = useForm<Record<string, any>>({})

const submitting = ref(false)

// Merge backend initial values + parent-provided data
watch(
    () => initialValues.value,
    (newValues) => {
        if (newValues) {
            form.clearErrors()
            form.reset()
            Object.assign(form.data(), {
                ...newValues,
                ...(props.initialData ?? {})
            })
        }
    },
    { immediate: true, deep: true }
)

// Reset form when entityId changes (create ↔ edit switch)
watch(
    () => props.entityId,
    () => {
        form.reset()
        reloadFields()
    }
)

// ────────────────────────────────────────────────
// Submission logic
// ────────────────────────────────────────────────
const submit = async () => {
    submitting.value = true
    form.clearErrors()

    const httpMethod = props.method || (props.entityId ? 'put' : 'post')
    const url = props.entityId
        ? props.submitUrl.replace('{id}', String(props.entityId))
        : props.submitUrl

    try {
        await form[httpMethod](url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: props.entityId ? 'Record updated' : 'Record created',
                    life: 4000
                })
                emit('submitted')
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0] as string | undefined
                if (firstError) {
                    toast.add({
                        severity: 'error',
                        summary: 'Validation Failed',
                        detail: firstError,
                        life: 6000
                    })
                }
                nextTick(() => focusFirstError())
            }
        })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Submission Failed',
            detail: err.message || 'An unexpected error occurred',
            life: 6000
        })
    } finally {
        submitting.value = false
    }
}

// ────────────────────────────────────────────────
// Accessibility & UX helpers
// ────────────────────────────────────────────────
const formId = computed(() => `dynamic-form-${props.model}-${props.entityId ?? 'new'}`)

const firstInvalidField = computed(() => Object.keys(form.errors)[0] ?? null)

const focusFirstError = () => {
    if (!firstInvalidField.value) return

    nextTick(() => {
        const selector = `[data-field-name="${firstInvalidField.value}"]`
        const el = document.querySelector(selector) as HTMLElement
        if (el) {
            el.focus()
            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
    })
}

watch(() => form.hasErrors, (hasErrors) => {
    if (hasErrors) focusFirstError()
})

// ────────────────────────────────────────────────
// States
// ────────────────────────────────────────────────
const showNoFieldsMessage = computed(
    () => !fieldsLoading.value && !fieldsError.value && categories.value.length === 0
)

const isFormReady = computed(
    () => !fieldsLoading.value && !fieldsError.value && categories.value.length > 0
)

onMounted(() => {
    // Auto-focus first input on create (after fields load)
    if (!props.entityId) {
        watch(isFormReady, (ready) => {
            if (ready) {
                nextTick(() => {
                    const firstInput = document.querySelector(
                        `#${formId.value} input:not([type="hidden"]), #${formId.value} textarea, #${formId.value} [role="combobox"]`
                    ) as HTMLElement
                    firstInput?.focus?.()
                })
            }
        })
    }
})
</script>

<template>
    <div class="dynamic-form-container">
        <!-- Optional title -->
        <h2 v-if="title" :id="`${formId}-title`"
            class="text-xl md:text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100">
            {{ title }}
        </h2>

        <!-- Loading state -->
        <div v-if="fieldsLoading" class="flex flex-col items-center justify-center py-20">
            <ProgressSpinner class="mb-6" />
            <p class="text-gray-600 dark:text-gray-400 text-lg">Loading custom fields...</p>
        </div>

        <!-- Fields load error -->
        <Message v-else-if="fieldsError" severity="error" :closable="false" class="mb-8 text-base">
            {{ fieldsError }}
            <Button label="Retry" icon="pi pi-refresh" text severity="danger" class="ml-4" @click="reloadFields" />
        </Message>

        <!-- Main form -->
        <form v-else :id="formId" @submit.prevent="submit" class="space-y-10" role="form"
            :aria-labelledby="title ? `${formId}-title` : undefined">
            <!-- Form-level error summary (accessibility + UX) -->
            <div v-if="form.hasErrors"
                class="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-xl p-5 shadow-sm"
                role="alert" aria-live="assertive">
                <p class="text-red-800 dark:text-red-200 font-medium mb-3 text-base">
                    Please fix the following errors:
                </p>
                <ul class="list-disc pl-6 space-y-1.5 text-sm text-red-700 dark:text-red-300">
                    <li v-for="(msg, field) in form.errors" :key="field">
                        <strong>{{ field.replace(/_/g, ' ').toUpperCase() }}:</strong> {{ msg }}
                    </li>
                </ul>
            </div>

            <!-- Categories / fields -->
            <div v-if="isFormReady" class="space-y-12">
                <section v-for="category in categories" :key="category.name"
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 md:p-8">
                    <h3
                        class="text-lg font-semibold mb-6 text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-3">
                        {{ category.label }}
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        <CustomFieldRenderer v-for="field in category.fields" :key="field.name" :field="field"
                            v-model="form[field.name]" :error="form.errors[field.name]" :data-field-name="field.name" />
                    </div>
                </section>
            </div>

            <!-- No fields defined -->
            <Message v-else-if="showNoFieldsMessage" severity="info" :closable="false" class="text-base">
                No custom fields have been configured for this resource yet.
            </Message>

            <!-- Form actions -->
            <div
                class="flex flex-col sm:flex-row sm:justify-end gap-4 pt-8 border-t border-gray-200 dark:border-gray-700">
                <Button type="button" label="Cancel" severity="secondary" text @click="emit('cancelled')"
                    :disabled="submitting || form.processing" />

                <Button type="submit" :label="entityId ? 'Update Record' : 'Create Record'"
                    :loading="submitting || form.processing"
                    :disabled="fieldsLoading || submitting || form.processing || !isFormReady" class="min-w-[160px]" />
            </div>
        </form>
    </div>
</template>

<style scoped>
.dynamic-form-container {
    @apply px-4 sm:px-6 lg:px-0;
}

section {
    @apply transition-all duration-200;
}

@media (max-width: 767px) {
    .grid {
        @apply grid-cols-1 gap-6;
    }
}
</style>
