<!-- resources/js/Components/Form/AsyncSelect.vue -->
<!--
  AsyncSelect.vue

  Purpose:
  - A reusable, fully-featured async select/multi-select component for dynamic dropdowns
  - Designed for cases where options are loaded from an API (e.g., search users, schools, subjects)
  - Supports both single and multiple selection
  - Features: server-side search, infinite scroll (load more), loading states, error handling
  - Built on top of useAsyncOptions composable for clean separation of concerns
  - Integrates seamlessly with PrimeVue Select/MultiSelect and your form system

  Key Features:
  - Debounced search (configurable delay)
  - Infinite scroll loading of additional pages
  - Preserves selected value(s) even during reloads
  - Displays user-friendly loading and error messages
  - Emits standard v-model updates
  - Fully typed with TypeScript

  Future improvements:
  - Add "selected label" display when no search results (common in multi-select)
  - Support placeholder customization via prop
  - Add clear button control
  - Allow custom empty state slot
  - Cache results per unique search_url + params (advanced)

  Usage Example:
  <AsyncSelect
    id="teacher"
    :field="customField"
    v-model="form.teacher_id"
    :invalid="form.errors.teacher_id"
  />
-->

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { MultiSelect, Select } from 'primevue';
import { useAsyncOptions } from '@/composables/useAsyncOptions';
import type { CustomField } from '@/types/form';

/** 
 * Props for AsyncSelect component
 */
const props = defineProps<{
    id: string;

    /**
     * Custom field configuration focused on async select behavior.
     * 
     * We use Omit to exclude all base/irrelevant fields from CustomField
     * and only keep the ones relevant for rendering + async loading.
     * Then intersect with required/optional async-specific fields.
     */
    field: Omit<
        CustomField,
        | 'id'
        | 'name'
        | 'field_type'
        | 'default_value'
        | 'description'
        | 'hint'
        | 'category'
        | 'label'
        | 'sort'
        | 'has_options'
        | 'rules'
        | 'classes'
        | 'options'
        | 'extra_attributes'
        | 'cast_as'
        | 'model_type'
        | 'school_id'
        | 'created_at'
        | 'updated_at'
        | 'deleted_at'
    > & {
        /** Required: API endpoint for searching options */
        search_url: string;

        /** Optional: Allow multiple selection */
        multiple?: boolean;

        placeholder?: string;

        /** Optional: Fine-tune async behavior and option mapping */
        field_options?: {
            option_label?: string;
            option_value?: string;
            search_key?: string;
            search_delay?: number;
            search_params?: Record<string, any>;
        };
    };

    modelValue: any;
    invalid?: boolean;
    disabled?: boolean;
}>();

// ------------------------------------------------------------------
// Emits
// ------------------------------------------------------------------
const emit = defineEmits<{
    (e: 'update:modelValue', value: any): void;
}>();

// ------------------------------------------------------------------
// Computed: Selection Mode & Option Mapping
// ------------------------------------------------------------------
const multiple = computed(() => props.field.multiple ?? false);

const optionLabel = computed(() => props.field.field_options?.option_label ?? 'label');

const optionValue = computed(() => props.field.field_options?.option_value ?? 'value');

// ------------------------------------------------------------------
// Async Options Logic via Composable
// ------------------------------------------------------------------
const {
    options,        // Reactive array of loaded options
    loading,        // True during API request
    error,          // Error message if request fails
    search,         // Function to trigger search (debounced)
    loadMore,       // Load next page on scroll-end
    hasMore,        // True if more pages available
} = useAsyncOptions({
    url: props.field.search_url,
    params: props.field.field_options?.search_params ?? {},
    searchKey: props.field.field_options?.search_key ?? 'search',
    delay: props.field.field_options?.search_delay ?? 400, // 400ms debounce
});

// ------------------------------------------------------------------
// Two-way v-model Sync
// ------------------------------------------------------------------
const internal = ref(props.modelValue);

// Emit changes upward
watch(internal, (newVal) => {
    emit('update:modelValue', newVal);
});

// Sync external changes (e.g., form reset) → internal
watch(
    () => props.modelValue,
    (newVal) => {
        internal.value = newVal;
    }
);

// ------------------------------------------------------------------
// Initial Load Behavior
// ------------------------------------------------------------------
onMounted(() => {
    // For single select: pre-load options if no value selected (better UX)
    // For multiple: usually starts empty, so only load on user interaction
    if (!multiple.value && !internal.value) {
        search(''); // Load initial full list
    }
    // For multiple, we wait for user to open dropdown or type
});
</script>

<template>
    <div class="relative">
        <!-- Multi-Select Mode -->
        <MultiSelect v-if="multiple" :id="id" v-model="internal" :options="options" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" display="chip" filter
            @filter="search($event.value)" @scroll-end="loadMore" class="w-full" :pt="{
                root: { class: 'w-full' },
                // Hide PrimeVue's default 'Load more' trigger – we use scroll-end
                loadMore: { class: 'hidden' }
            }" :placeholder="field.placeholder ?? 'Select items...'" :show-clear="true" />

        <!-- Single Select Mode -->
        <Select v-else :id="id" v-model="internal" :options="options" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" filter
            @filter="search($event.value)" @scroll-end="loadMore" class="w-full" :placeholder="field.placeholder ?? 'Select an option...'"
            :show-clear="true" />

        <!-- Error Message -->
        <small v-if="error" class="text-red-600 text-xs mt-1 block">
            {{ error }}
        </small>

        <!-- Initial Loading State (when no options yet) -->
        <div v-if="loading && options.length === 0"
            class="absolute inset-x-0 bottom-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 py-3 text-center text-sm text-gray-500">
            Loading options...
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Ensure consistent height and alignment */
:deep(.p-multiselect),
:deep(.p-select) {
    @apply h-11;
}

/* Improve chip appearance in multi-select */
:deep(.p-multiselect-chip) {
    @apply bg-primary/10 text-primary border-primary/20;
}
</style>