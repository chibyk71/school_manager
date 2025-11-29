<!-- resources/js/components/forms/DynamicInput.vue -->
<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue';
import {
    InputText,
    Password,
    InputNumber,
    Textarea,
    Checkbox,
    RadioButton,
    Select,
    MultiSelect,
    FileUpload,
    ColorPicker,
    ToggleSwitch,
    DatePicker,
} from 'primevue';
import AsyncSelect from './AsyncSelect.vue';
import InputWrapper from './InputWrapper.vue';
import { type CustomField, isOptionField, isAsyncSelectField } from '@/types/form';

// ------------------------------------------------------------------
// Safe JSON parser
// ------------------------------------------------------------------
const parseJson = (json: string | null | undefined): any => {
    if (!json) return null;
    try {
        return JSON.parse(json);
    } catch {
        return null;
    }
};

// ------------------------------------------------------------------
// Props & Emit
// ------------------------------------------------------------------
const props = defineProps<{
    field: CustomField;
    modelValue: any;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: any): void;
}>();

// ------------------------------------------------------------------
// Default value & options parsing
// ------------------------------------------------------------------
const parsedDefault = computed(() => {
    if (props.field.default_value === null) return null;
    const parsed = parseJson(props.field.default_value);
    return parsed ?? props.field.default_value;
});

const parsedOptions = computed(() => {
    if (!isOptionField(props.field)) return [];
    const raw = props.field.options;
    if (Array.isArray(raw)) return raw;
    return parseJson(raw as any) || [];
});

// ------------------------------------------------------------------
// Internal v-model with casting
// ------------------------------------------------------------------
const internal = ref<any>(props.modelValue ?? parsedDefault.value);

watch(internal, (val) => {
    let casted = val;

    if (props.field.cast_as === 'integer') {
        casted = val !== null && val !== '' ? parseInt(val as string, 10) : null;
    } else if (props.field.cast_as === 'boolean') {
        casted = !!val;
    } else if (props.field.cast_as === 'json') {
        casted = typeof val === 'object' && val !== null ? val : parseJson(val);
    }

    emit('update:modelValue', casted);
});

watch(() => props.modelValue, (v) => (internal.value = v), { immediate: true });

// ------------------------------------------------------------------
// Component map – fully typed
// ------------------------------------------------------------------
const componentMap = {
    text: InputText,
    password: Password,
    email: InputText,
    number: InputNumber,
    textarea: Textarea,
    date: DatePicker,
    select: Select,
    multiselect: MultiSelect,
    switch: ToggleSwitch,
    color: ColorPicker,
    file: FileUpload,
    checkbox: Checkbox,
    radio: RadioButton,
} as const;

type FieldTypeKey = keyof typeof componentMap;
const resolvedComponent = computed<Component>(() => {
    const type = props.field.field_type as FieldTypeKey;
    return componentMap[type] ?? InputText;
});
</script>

<template>
    <InputWrapper :field="field" :error="error">
        <template #default="{ id, invalid, disabled, readonly }">
            <!-- Async Select -->
            <AsyncSelect v-if="isAsyncSelectField(field)" :id="id" :field="field" v-model="internal" :invalid="invalid"
                :disabled="disabled" />

            <!-- File Upload -->
            <FileUpload v-else-if="field.field_type === 'file'" :id="id" mode="basic" :multiple="field.multiple"
                :disabled="disabled || readonly" :invalid="invalid" choose-label="Choose File"
                @select="(e: any) => internal = field.multiple ? e.files : e.files[0]" class="w-full" />

            <!-- Checkbox Group -->
            <div v-else-if="field.field_type === 'checkbox'" class="flex flex-wrap gap-4">
                <div v-for="opt in parsedOptions" :key="opt.value" class="flex items-center gap-2">
                    <Checkbox :inputId="`${id}-${opt.value}`" :value="opt.value" v-model="internal"
                        :binary="!field.multiple" :disabled="disabled || readonly" />
                    <label :for="`${id}-${opt.value}`" class="cursor-pointer text-sm">
                        {{ opt.label }}
                    </label>
                </div>
            </div>

            <!-- Radio Group -->
            <div v-else-if="field.field_type === 'radio'" class="flex flex-wrap gap-4">
                <div v-for="opt in parsedOptions" :key="opt.value" class="flex items-center gap-2">
                    <RadioButton :inputId="`${id}-${opt.value}`" :value="opt.value" v-model="internal"
                        :disabled="disabled || readonly" />
                    <label :for="`${id}-${opt.value}`" class="cursor-pointer text-sm">
                        {{ opt.label }}
                    </label>
                </div>
            </div>

            <!-- All Standard Components (text, select, etc.) -->
            <component :is="resolvedComponent" :id="id" v-model="internal" :options="parsedOptions" optionLabel="label"
                optionValue="value" :multiple="field.field_type === 'multiselect'"
                :placeholder="field.placeholder ?? ''" :invalid="invalid" :disabled="disabled || readonly" fluid
                class="w-full" />
        </template>
    </InputWrapper>
</template>

<style scoped>
:deep(.p-fileupload-basic) {
    @apply w-full;
}
</style>
