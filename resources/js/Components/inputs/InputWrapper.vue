<script setup lang="ts">
import { type Field } from '@/types';
import { InputText, Message } from 'primevue';
import { computed, defineProps, readonly } from 'vue';

const props = defineProps<Field & {
    error?: string;
    required?: boolean,
    disabled?: boolean,
    field_type?: string,
    noedit?: boolean,
    name?: string,
}>()

const invalid = computed(() => !!props.error)

const label = computed(() => {
    return props.label ?? props.name
})

const id = `input-${Math.round(Math.random()).toString(36).substring(7)}`
const type = computed(() => props.field_type ?? 'text');
const name = computed(() => props.name ?? props.label);

const inputSlotPass = {
    id,
    invalid: invalid.value,
    type: type,
    placeholder: props.placeholder,
    name: name,
    options: props.options,
    value: props.default_value,
    hint: props.hint,
    required: props.required,
    disabled: props.disabled,
}

const model = defineModel<string|null>()

model.value = props.default_value ?? ''
</script>

<template>
    <div class="mb-3">
        <label :for="id">{{ label }} <span v-if="required" class="text-red-500 text-sm">*</span>
            <span v-if="hint" class="text-gray-400 text-sm" :title="hint"><i class="ti ti-info-circle"></i></span>
        </label>
        <slot name="input" v-bind="inputSlotPass">
            <InputText :type="field_type?? 'text'" :disabled="disabled" :readonly="noedit" v-model="model" :required="required" :id :invalid fluid :name />
        </slot>
        <Message variant="simple" severity="error" v-if="invalid"> {{ error ?? hint }}</Message>
    </div>
</template>
