<!-- SmartInput.vue -->
<template>
    <div class="mb-4">
        <label :for="field.name" class="block font-medium mb-1">{{ field.label }}</label>

        <component :is="inputComponent" v-model="modelValue" :options="parsedOptions" :placeholder="field.placeholder"
            class="w-full" :class="field.classes" />

        <small v-if="error" class="p-error">{{ error }}</small>
        <small v-if="field.hint" class="p-hint">{{ field.hint }}</small>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Dropdown from "primevue/dropdown";
import Checkbox from "primevue/checkbox";
import Textarea from "primevue/textarea";

import type { Field } from "@/types";

const props = defineProps<{
    field: Field;
    modelValue: any;
    error?: string;
}>();
const emit = defineEmits(["update:modelValue"]);

const inputComponent = computed(() => {
    switch (props.field.field_type) {
        case "number":
            return InputNumber;
        case "select":
            return Dropdown;
        case "checkbox":
            return Checkbox;
        case "textarea":
            return Textarea;
        default:
            return InputText;
    }
});

const parsedOptions = computed(() => {
    if (typeof props.field.options === "string") {
        try {
            return JSON.parse(props.field.options);
        } catch {
            return [];
        }
    }
    return props.field.options || [];
});

const updateValue = (value: any) => {
    emit("update:modelValue", value);
};
</script>
