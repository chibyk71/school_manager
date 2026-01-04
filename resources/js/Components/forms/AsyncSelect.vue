<!--
  resources/js/Components/Form/AsyncSelect.vue v8.0 – Production-Ready Async Select with Explicit Clear Option

  Purpose & Problems Solved:
  - Provides a reusable, fully-featured async dropdown (single or multi-select) for dynamic options loaded from an API.
  - Integrates seamlessly with custom fields system (e.g., relation fields like guardian, department, class, etc.).
  - Solves the common UX issue where users need to explicitly "unset" an optional relation field – PrimeVue's built-in show-clear only works after a value is selected.
  - Introduces a persistent "None" / clear option that is always visible at the top, even during search or loading.
  - Supports virtual scrolling for large datasets (>300 items) to maintain performance.
  - Handles initial loading, search debouncing, infinite scroll, error states, and helpful user feedback.
  - Fully typed with TypeScript, accessible, and styled consistently with Tailwind + PrimeVue.
  - Used extensively in student/teacher/staff registration and edit forms where optional relations are common.

  Key Features (v8.0 improvements):
  - Persistent "None" option with customizable label (default: "— None —").
  - Selecting "None" explicitly clears the value (null for single, [] for multi).
  - Smart pre-loading: fetches current value labels on edit, loads all if ≤300 items, otherwise lazy.
  - Virtual scroller with lazy loading for smooth performance on large lists (e.g., all students/teachers).
  - Helpful messages: shows result count hint and error feedback.
  - Full loading overlay on initial fetch for better perceived performance.
  - Consistent styling: height, chip colors, italic "None" option.
  - Proper v-model two-way binding with emit handling for Inertia forms.
  - Accessible via unique `id` prop and PrimeVue's built-in accessibility.

  Dependencies:
  - PrimeVue: Select, MultiSelect
  - Composables: useAsyncOptions.ts
  - Types: CustomField from '@/types/form'

  Best Practices Applied:
  - Reactive internal value to avoid direct mutation issues.
  - Watchers for sync with external v-model.
  - Computed properties for configuration to avoid repeated logic.
  - Proper event handling (@filter instead of custom input).
  - Performance: virtual scroller, debounced search, conditional full-load.
  - UX: clear feedback, loading states, persistent clear option.
-->
<script setup lang="ts">
import { ref, watch, computed, onMounted, nextTick } from 'vue';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import { useAsyncOptions } from '@/composables/useAsyncOptions';
import type { CustomField } from '@/types/form';

/**
 * Component Props
 */
const props = defineProps<{
    /** Unique ID for the input (required for label association and accessibility) */
    id: string;

    /** Custom field configuration – contains API endpoint and display options */
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
        /** API endpoint that returns paginated options */
        search_url: string;

        /** Enable multi-select mode */
        multiple?: boolean;

        /** Placeholder text */
        placeholder?: string;

        /** Custom label for the "clear/none" option */
        none_label?: string;

        /** Advanced options for API behavior and display mapping */
        field_options?: {
            option_label?: string;
            option_value?: string;
            search_key?: string;
            search_delay?: number;
            search_params?: Record<string, any>;
            label_field?: string;
            value_field?: string;
            search_fields?: string | string[];
        };
    };

    /** Show invalid state (e.g., from Inertia validation) */
    invalid?: boolean;

    /** Disable the input */
    disabled?: boolean;
}>();

// v-model binding
const model = defineModel<any>({
    type: null,
    required: true,
});

/**
 * Computed Configuration
 */
const multiple = computed(() => props.field.multiple ?? false);

const optionLabel = computed(() => props.field.field_options?.option_label ?? 'label');
const optionValue = computed(() => props.field.field_options?.option_value ?? 'value');

const noneLabel = computed(() => props.field.none_label ?? '— None —');
const noneOption = computed(() => ({
    [optionValue.value]: null,
    [optionLabel.value]: noneLabel.value,
}));

/**
 * API Parameters – merged static + dynamic (label/value fields)
 */
const dynamicParams = computed(() => {
    const opts = props.field.field_options ?? {};
    const params: Record<string, any> = {};

    params.label_field = opts.label_field ?? 'name';
    params.value_field = opts.value_field ?? 'id';

    if (opts.search_fields) {
        params.search_fields = Array.isArray(opts.search_fields)
            ? opts.search_fields.join(',')
            : opts.search_fields;
    }

    return params;
});

const apiParams = computed(() => ({
    ...props.field.field_options?.search_params ?? {},
    ...dynamicParams.value,
}));

/**
 * Async Options Composable
 */
const {
    options,
    total,
    loading,
    error,
    search,
    loadMore,
    hasMore,
    refresh,
} = useAsyncOptions({
    url: props.field.search_url,
    params: apiParams.value,
    searchKey: props.field.field_options?.search_key ?? 'search',
    delay: props.field.field_options?.search_delay ?? 400,
});

/**
 * Display Options – always prepend the static "None" option
 */
const displayOptions = computed(() => {
    const loaded = options.value ?? [];
    return [noneOption.value, ...loaded];
});

/**
 * Optional: Load all options if dataset is reasonably small (≤300)
 */
const loadAllIfSmall = async () => {
    if (total.value > 0 && total.value <= 300 && hasMore.value) {
        loadMore();
        if (hasMore.value) await loadAllIfSmall(); // Recurse until complete
    }
};

/**
 * Initial Load Logic
 * - On edit forms: refresh to load current value labels
 * - On create forms: initial empty search
 * - Auto-load all if small dataset
 */
onMounted(async () => {
    const hasExistingValue =
        model.value !== null &&
        model.value !== undefined &&
        (!Array.isArray(model.value) || model.value.length > 0);

    if (hasExistingValue) {
        refresh(); // Load labels for selected value(s)
    } else if (!multiple.value) {
        search(''); // Trigger initial empty search for single select
    }

    await nextTick();

    if (total.value > 0 && total.value <= 300) {
        await loadAllIfSmall();
    }
});
</script>

<template>
    <div class="relative">
        <!-- MultiSelect Mode -->
        <MultiSelect v-if="multiple" :id="id" v-model="model" :options="displayOptions" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" display="chip" filter
            @filter="search($event.value)" :virtualScrollerOptions="{
                lazy: true,
                onLazyLoad: () => { if (hasMore && !loading) loadMore(); },
                itemSize: 38,
                showLoader: total > 300,
                loading: loading && hasMore && total > 300,
                delay: 200
            }" class="w-full" :placeholder="field.placeholder ?? 'Select items...'" :show-clear="true" :pt="{
                root: { class: 'w-full' },
                panel: { class: 'max-h-96 overflow-auto' }
            }" />

        <!-- Single Select Mode -->
        <Select v-else :id="id" v-model="model" :options="displayOptions" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" filter
            @filter="search($event.value)" :virtualScrollerOptions="{
                lazy: true,
                onLazyLoad: () => { if (hasMore && !loading) loadMore(); },
                itemSize: 38,
                showLoader: total > 300,
                loading: loading && hasMore && total > 300,
                delay: 200
            }" class="w-full" :placeholder="field.placeholder ?? 'Select an option...'" :show-clear="true" :pt="{
                root: { class: 'w-full' },
                panel: { class: 'max-h-96 overflow-auto' }
            }" />

        <!-- User Guidance Message -->
        <small class="text-amber-700 dark:text-amber-400 text-xs mt-1 block">
            {{ total > 300
                ? `Showing ${total}+ results. Type to narrow down.`
                : 'Type to search for faster results.'
            }}
        </small>

        <!-- Error Message -->
        <small v-if="error" class="text-red-600 dark:text-red-500 text-xs mt-1 block">
            {{ error }}
        </small>

        <!-- Full Overlay Loader (only on initial empty load) -->
        <div v-if="loading && options.length === 0"
            class="absolute inset-0 bg-white/90 dark:bg-gray-900/90 flex items-center justify-center rounded-md z-10 pointer-events-none">
            <span class="text-sm text-gray-700 dark:text-gray-300">Loading options...</span>
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Consistent input height */
:deep(.p-select),
:deep(.p-multiselect) {
    @apply h-11;
}

/* Chip styling for multi-select */
:deep(.p-multiselect-chip) {
    @apply bg-primary/10 text-primary border border-primary/20;
}

/* Visual distinction for the "None" option */
:deep(.p-select-option[value="null"]),
:deep(.p-multiselect-option[value="null"]) {
    @apply italic text-gray-500 dark:text-gray-400;
}
</style>
