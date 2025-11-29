<!-- resources/js/components/forms/InputWrapper.vue -->
<script setup lang="ts">
import { computed, useSlots } from 'vue';
import { IconField, InputIcon, Message } from 'primevue';
import type { CustomField } from '@/types/form';

const props = defineProps<{
    field: CustomField;
    error?: string;
    label?: string;
    disabled?: boolean;
    readonly?: boolean;
    prefix_icon?: string;
    suffix_icon?: string;
}>();

const slots = useSlots();

// ------------------------------------------------------------------
// Safe access helpers (handles nullable DB fields)
// ------------------------------------------------------------------
const safeLabel = computed(() =>
    props.label ?? props.field.label ?? props.field.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
);

const safeHint = computed(() => props.field.hint ?? null);
const safeDescription = computed(() => props.field.description ?? null);

// Icons – safely fallback
const prefixIcon = computed(() => props.prefix_icon || props.field.icon || null);
const suffixIcon = computed(() => props.suffix_icon || null);

// Required detection from rules or required flag
const isRequired = computed(() => {
    if (props.field.extra_attributes?.required === true) return true;
    if (Array.isArray(props.field.rules)) {
        return props.field.rules.includes('required') ||
            props.field.rules.some(r => r.startsWith('required'));
    }
    return false;
});

const hasError = computed(() => !!props.error);

// Unique ID
const id = computed(() => `field-${props.field.name}-${props.field.id ?? Math.random().toString(36).substr(2, 5)}`);

// Classes – convert array to string if needed
const fieldClasses = computed(() => {
    if (Array.isArray(props.field.classes)) {
        return props.field.classes.join(' ');
    }
    return props.field.classes ?? '';
});

// Extra attributes – safe spread
const extraAttrs = computed(() => props.field.extra_attributes ?? {});
</script>

<template>
    <div class="flex flex-col gap-2 field-wrapper"
        :class="[fieldClasses, { 'field-required': isRequired, 'field-error': hasError }]">
        <!-- Label + Required + Hint -->
        <label v-if="safeLabel" :for="id"
            class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2 cursor-pointer select-none">
            <span>{{ safeLabel }}</span>
            <span v-if="isRequired" class="text-red-600 font-bold text-xs ml-1">*</span>
            <i v-if="safeHint" v-tooltip.top="{ value: safeHint, escape: true }"
                class="pi pi-info-circle text-gray-400 hover:text-primary cursor-help text-xs" aria-hidden="true" />
        </label>

        <!-- Description -->
        <p v-if="safeDescription" class="text-xs text-gray-500 dark:text-gray-400 -mt-1 mb-2"
            v-html="safeDescription" />

        <!-- Main Input Area -->
        <IconField :class="{ 'p-invalid': hasError }" :disabled="disabled || field.extra_attributes.disabled" class="w-full"
            icon-position="both">
            <!-- Prefix Icon -->
            <InputIcon v-if="prefixIcon" class="text-gray-500">
                <i :class="prefixIcon" />
            </InputIcon>

            <!-- Default Slot: DynamicInput, AsyncSelect, etc. -->
            <slot :id="id" :name="field.name" :invalid="hasError" :disabled="disabled || field.extra_attributes.disabled"
                :readonly="readonly || field.extra_attributes.readonly" v-bind="extraAttrs" />

            <!-- Suffix Icon -->
            <InputIcon v-if="suffixIcon" class="text-gray-500">
                <i :class="suffixIcon" />
            </InputIcon>
        </IconField>

        <!-- Error Message -->
        <Message v-if="hasError" severity="error" :closable="false" variant="simple" class="mt-1 text-xs">
            {{ error }}
        </Message>

        <!-- Fallback Hint -->
        <small v-else-if="safeHint && !slots.hint" class="text-xs text-gray-500 dark:text-gray-400">
            {{ safeHint }}
        </small>
    </div>
</template>

<style scoped>
.field-wrapper {
    @apply mb-6;
}

.field-error :deep(.p-inputtext),
.field-error :deep(.p-select),
.field-error :deep(.p-multiselect) {
    @apply border-red-500 focus:border-red-600;
}

:deep(.p-iconfield input:focus),
:deep(.p-iconfield .p-inputtext:focus),
:deep(.p-select:focus),
:deep(.p-multiselect:focus) {
    @apply ring-2 ring-primary ring-offset-2 transition-all;
}
</style>
