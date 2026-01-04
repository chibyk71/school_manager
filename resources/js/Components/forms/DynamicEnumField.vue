<!-- resources/js/Components/forms/DynamicEnumField.vue -->
<script setup lang="ts">
/**
 * DynamicEnumField.vue
 *
 * Reusable form field component for rendering dynamic enum properties.
 *
 * Features / Problems Solved:
 * - Single component replaces the need for separate DynamicSelect.vue and DynamicRadio.vue.
 * - Automatically chooses the best PrimeVue input type based on the number of options:
 *     • ≤ 4 options → RadioButton group (better UX for small sets – e.g., gender, title).
 *     • > 4 options → Dropdown (more compact for longer lists – e.g., address types, departments).
 * - Full v-model support (two-way binding) with proper emits.
 * - Integrates directly with useDynamicEnums composable for on-demand, tenant-aware option loading.
 * - Reactive states: loading spinner, error message, empty state handling.
 * - Accessibility: proper labels, ARIA attributes via PrimeVue, keyboard navigation.
 * - Responsive: Tailwind classes ensure good layout on mobile/desktop; radios wrap naturally.
 * - Consistent styling: matches your app's PrimeVue + Tailwind theme (badges for colors if provided).
 * - Optional props for overriding behavior (e.g., force dropdown/radio, custom placeholder).
 * - Graceful fallbacks: shows loading → options → error/empty message.
 * - Performance: loads options only once on mount (cached by composable).
 *
 * Fits into the DynamicEnums Module:
 * - Primary UI component for rendering any dynamic enum property in forms
 *   (create/edit Profile, Address, etc., inside ResourceDialog.vue or full pages).
 * - Eliminates duplication: one component handles both dropdown and radio use cases intelligently.
 * - Works seamlessly with Inertia forms, useModalForm.ts, and server-side InDynamicEnum validation.
 * - Production-ready: responsive, accessible, type-safe, and aligned with PrimeVue best practices.
 * - Usage example:
 *     <DynamicEnumField
 *         v-model="form.gender"
 *         model="App\\Models\\Profile"
 *         property="gender"
 *         label="Gender"
 *     />
 */

import { computed, onMounted, watch } from 'vue';
import { useDynamicEnums } from '@/composables/useDynamicEnums';
import Dropdown from 'primevue/dropdown';
import RadioButton from 'primevue/radiobutton';
import ProgressSpinner from 'primevue/progressspinner';
import { Select } from 'primevue';

interface Props {
    /** Fully qualified model class (e.g., 'App\\Models\\Profile') */
    model: string;

    /** Dynamic enum property name (e.g., 'gender', 'title') */
    property: string;

    /** Field label for accessibility and UI */
    label?: string;

    /** Optional placeholder for dropdown */
    placeholder?: string;

    /** Force specific input type: 'dropdown' | 'radio' | 'auto' (default: 'auto') */
    mode?: 'auto' | 'dropdown' | 'radio';

    /** Show label as floating (PrimeVue default) or inline for radios */
    inline?: boolean;

    /** Disabled state */
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: '',
    placeholder: 'Select an option',
    mode: 'auto',
    inline: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

const { options, loading, error, load } = useDynamicEnums();

// Computed v-model
const localValue = defineModel({required: true, type: [String, null] });

// Determine rendering mode
const renderMode = computed(() => {
    if (props.mode !== 'auto') return props.mode;
    return options.value.length <= 4 ? 'radio' : 'dropdown';
});

// Load options on mount
onMounted(async () => {
    await load(props.model, props.property);
});

// Optional: reload if props change (rare but safe)
watch(() => [props.model, props.property], async () => {
    await load(props.model, props.property);
});
</script>

<template>
    <div class="space-y-2">
        <!-- Label -->
        <label v-if="label && renderMode === 'dropdown'" class="block text-sm font-medium text-gray-700">
            {{ label }}
        </label>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-4">
            <ProgressSpinner style="width: 32px; height: 32px" />
            <span class="ml-2 text-gray-600">Loading options...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-red-600 text-sm">
            {{ error }}
        </div>

        <!-- Empty State -->
        <div v-else-if="options.length === 0" class="text-gray-500 text-sm">
            No options available.
        </div>

        <!-- Dropdown Mode -->
        <Select v-else-if="renderMode === 'dropdown'" v-model="localValue" :options="options" optionLabel="label"
            optionValue="value" :placeholder="placeholder" :loading="loading" :disabled="disabled || loading"
            class="w-full" :class="{ 'opacity-50': disabled }" showClear>
            <template #option="slotProps">
                <div class="flex items-center">
                    <span v-if="slotProps.option.color" :class="slotProps.option.color"
                        class="inline-block w-3 h-3 rounded-full mr-2"></span>
                    {{ slotProps.option.label }}
                </div>
            </template>
        </Select>

        <!-- Radio Mode -->
        <div v-else class="space-y-3">
            <div v-if="!inline && label" class="block text-sm font-medium text-gray-700 mb-2">
                {{ label }}
            </div>
            <div :class="inline ? 'flex flex-wrap gap-6' : 'space-y-3'">
                <div v-for="option in options" :key="option.value" class="flex items-center">
                    <RadioButton v-model="localValue" :inputId="option.value" :value="option.value"
                        :disabled="disabled" />
                    <label :for="option.value" class="ml-2 text-sm text-gray-700 cursor-pointer select-none"
                        :class="{ 'opacity-50': disabled }">
                        <span v-if="option.color" :class="option.color"
                            class="inline-block w-3 h-3 rounded-full mr-1 align-middle"></span>
                        {{ option.label }}
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Optional: improve radio group spacing on small screens */
@media (max-width: 640px) {
    .flex.flex-wrap.gap-6>div {
        @apply w-full;
    }
}
</style>
