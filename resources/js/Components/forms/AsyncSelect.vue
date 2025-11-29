<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { MultiSelect, Select } from 'primevue';
import { useAsyncOptions } from '@/composables/useAsyncOptions';
import type { CustomField } from '@/types/form';

const props = defineProps<{
    id: string;
    field: CustomField & { search_url: string };
    modelValue: any;
    invalid?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: any): void;
}>();

const multiple = computed(() => props.field.multiple ?? false);
const optionLabel = computed(() => props.field.field_options?.option_label ?? 'label');
const optionValue = computed(() => props.field.field_options?.option_value ?? 'value');

const {
    options,
    loading,
    error,
    search,
    loadMore,
    hasMore,
} = useAsyncOptions({
    url: props.field.search_url,
    params: props.field.field_options?.search_params ?? {},
    searchKey: props.field.field_options?.search_key ?? 'search',
    delay: props.field.field_options?.search_delay ?? 400,
});

const internal = ref(props.modelValue);

watch(internal, (val) => emit('update:modelValue', val));
watch(() => props.modelValue, (val) => internal.value = val);

onMounted(() => {
    if (!multiple.value && !internal.value) {
        search('');
    }
});
</script>

<template>
    <div class="relative">
        <MultiSelect v-if="multiple" :id="id" v-model="internal" :options="options" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" display="chip" filter
            @filter="search($event.value)" @scroll-end="loadMore" :pt="{
                root: { class: 'w-full' },
                loadMore: { class: 'hidden' }
            }" class="w-full" />

        <Select v-else :id="id" v-model="internal" :options="options" :optionLabel="optionLabel"
            :optionValue="optionValue" :loading="loading" :disabled="disabled" :invalid="invalid" filter
            @filter="search($event.value)" @scroll-end="loadMore" class="w-full" />

        <small v-if="error" class="text-red-600 text-xs mt-1">{{ error }}</small>
        <div v-if="loading && options.length === 0" class="p-4 text-center text-gray-500 text-sm">
            Loading options...
        </div>
    </div>
</template>
