<!--
  resources/js/components/forms/InputWrapper.vue

  Purpose / Role in the module:
  ────────────────────────────────────────────────────────────────
  Single, reusable wrapper component that provides consistent layout,
  labeling, validation feedback, hint/description display, required
  indicator, prefix/suffix icons, and error messaging for ALL dynamic
  custom fields — regardless of input type.

  This component eliminates massive duplication that would otherwise
  appear in every field renderer or form. It acts as the standardized
  "field shell" used by DynamicForm.vue and the future form builder.

  Solves / Features implemented:
  • Consistent label + required asterisk + hint icon placement
  • Automatic required detection (from field.required OR rules array)
  • Unified error display using PrimeVue Message (small & inline)
  • Support for prefix/suffix icons via PrimeVue IconField
  • Safe fallbacks for missing label/hint/description
  • Full Tailwind + custom CSS styling with dark mode support
  • Accessibility: proper for/id, aria-describedby for errors
  • Slot-based — child components (InputText, Dropdown, Calendar, etc.)
    receive id, name, invalid state, disabled/readonly props automatically
  • Responsive: works well in narrow modals and wide forms
  • Handles Inertia form errors (string | string[] | null)
  • Exposes slot props so inputs can bind correctly without repetition

  Integration points:
  • Used inside DynamicForm.vue via <InputWrapper v-for="field">
  • Will be used in CustomFieldModal.vue for field preview
  • Will be used in form builder preview pane
  • Aligns with backend CustomField model (name, label, hint, rules, etc.)

  Usage example:
  <InputWrapper :field="field" :error="form.errors[field.name]">
    <InputText v-model="form[field.name]" />
  </InputWrapper>

  Or for more complex fields:
  <InputWrapper :field="field" :error="form.errors.profile_photo">
    <FileUpload mode="basic" name="profile_photo" />
  </InputWrapper>
-->

<script setup lang="ts">
import { computed, useSlots, useAttrs } from 'vue'
import { IconField, InputIcon, Message } from 'primevue'
import type { CustomField } from '@/types/custom-fields' // adjust path if needed
import InputLabel from './InputLabel.vue';

const props = withDefaults(defineProps<{
    /**
     * The custom field definition coming from backend / useCustomFields
     */
    field: CustomField

    /**
     * Error message(s) — usually from Inertia form.errors[field.name]
     */
    error?: string | string[] | null

    /**
     * Optional override for label (rarely used)
     */
    label?: string

    disabled?: boolean
    readonly?: boolean

    /**
     * Optional override for prefix/suffix icons (pi-* classes)
     */
    prefixIcon?: string
    suffixIcon?: string
}>(), {
    error: null,
    label: undefined,
    disabled: false,
    readonly: false,
    prefixIcon: undefined,
    suffixIcon: undefined
})

const slots = useSlots()
const attrs = useAttrs()

// ────────────────────────────────────────────────
// Computed properties
// ────────────────────────────────────────────────

/**
 * Unique field ID — used for label-for and aria-describedby
 */
const fieldId = computed(() => {
    // Prefer backend-provided id if available; fallback to stable random
    const suffix = props.field.id
        ? String(props.field.id)
        : crypto.randomUUID().slice(0, 8)
    return `custom-field-${props.field.name}-${suffix}`
})

/**
 * Display label with smart fallback
 */
const displayLabel = computed(() => {
    return (
        props.label ??
        props.field.label ??
        props.field.name
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase())
    )
})

/**
 * Detect required state from multiple sources
 */
const isRequired = computed<boolean>(() => {
    if (props.field.required === true) return true
    if (props.field.extra_attributes?.required === true) return true

    if (Array.isArray(props.field.rules)) {
        return props.field.rules.some(rule =>
            rule === 'required' ||
            rule.startsWith('required:') ||
            rule.includes('|required') ||
            rule.includes('required|')
        )
    }

    return false
})

/**
 * Whether the field currently has an error
 */
const hasError = computed<boolean>(() => {
    if (!props.error) return false
    if (typeof props.error === 'string') return props.error.trim().length > 0
    if (Array.isArray(props.error)) return props.error.some(e => !!e?.trim())
    return false
})

/**
 * Normalized single error message to display
 */
const errorMessage = computed<string>(() => {
    if (!hasError.value) return ''
    if (typeof props.error === 'string') return props.error.trim()
    return (props.error as string[])[0]?.trim() ?? 'This field is invalid'
})

/**
 * Should we show the hint text?
 */
const showHint = computed<boolean>(() => {
    return !!props.field.hint && !hasError.value
})

/**
 * Combined wrapper classes
 */
const wrapperClasses = computed(() => [
    'field-wrapper mb-6 last:mb-0 transition-all duration-150',
    props.field.classes ?? '',
    { 'field-required': isRequired.value },
    { 'field-error': hasError.value }
])

/**
 * Classes applied to the IconField / input container
 */
const inputContainerClasses = computed(() => [
    'w-full',
    { 'p-invalid': hasError.value }
])

/**
 * Props passed to the default slot (the actual input component)
 */
const slotProps = computed(() => ({
    id: fieldId.value,
    name: props.field.name,
    invalid: hasError.value,
    disabled: props.disabled || !!props.field.extra_attributes?.disabled,
    readonly: props.readonly || !!props.field.extra_attributes?.readonly,
    'aria-describedby': hasError.value ? `${fieldId.value}-error` : undefined,
    // Merge any extra HTML attrs from backend
    ...attrs,
    ...props.field.extra_attributes
}))

/**
 * Icon to use for prefix (field.icon takes precedence if present)
 */
const effectivePrefixIcon = computed(() => props.prefixIcon || props.field.icon)
</script>

<template>
    <div :class="wrapperClasses">
        <!-- Label + required indicator + hint icon -->
         <InputLabel v-if="displayLabel" :for="fieldId" :value="displayLabel" :required="isRequired" :hint="field.hint" />

        <!-- IconField container with prefix/suffix support -->
        <IconField :class="inputContainerClasses" icon-position="left" :disabled="slotProps.disabled">
            <!-- Prefix icon -->
            <InputIcon v-if="effectivePrefixIcon" class="text-gray-500 dark:text-gray-400">
                <i :class="effectivePrefixIcon" />
            </InputIcon>

            <!-- The actual input / custom component goes here -->
            <slot v-bind="slotProps" />

            <!-- Suffix icon -->
            <InputIcon v-if="suffixIcon" class="text-gray-500 dark:text-gray-400">
                <i :class="suffixIcon" />
            </InputIcon>
        </IconField>

        <!-- Error message (PrimeVue small inline style) -->
        <Message v-if="hasError" :id="`${fieldId}-error`" severity="error" variant="simple" :closable="false"
            class="mt-1.5 text-xs leading-tight">
            {{ errorMessage }}
        </Message>

        <!-- Hint text when no error -->
        <p v-else-if="showHint" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
            {{ props.field.hint }}
        </p>

        <!-- Optional longer description (rendered safely) -->
        <p v-if="props.field.description && !hasError" class="mt-2 text-xs italic text-gray-500 dark:text-gray-400"
            v-html="props.field.description" />
    </div>
</template>

<style scoped>
/* Custom overrides / enhancements */
.field-wrapper {
    @apply relative;
}

.field-error :deep(.p-inputtext),
.field-error :deep(.p-inputnumber-input),
.field-error :deep(.p-datepicker-input),
.field-error :deep(.p-dropdown),
.field-error :deep(.p-multiselect),
.field-error :deep(.p-inputswitch) {
    @apply border-red-400 focus:border-red-500 focus:ring-red-200 dark:border-red-500 dark:focus:border-red-400;
}

:deep(.p-iconfield .p-input-icon) {
    @apply text-gray-400 dark:text-gray-500;
}

.field-required label::after {
    content: '';
}
</style>
