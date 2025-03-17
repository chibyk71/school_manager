<script setup lang="ts">
import { type Field } from '@/types';
import { InputText, Message } from 'primevue';
import { computed, defineProps } from 'vue';

const props = defineProps<Field & {
    error?: string;
    required?: boolean
}>()

const invalid = computed(() => !!props.error)

const label = computed(() => {
    return props.label ?? props.name
})

const id = `input-${Math.round(Math.random()).toString(36).substring(7)}`

const inputSlotPass = {
    id,
    invalid: invalid.value,
    type: props.field_type,
    placeholder: props.placeholder,
    name: props.name,
    options: props.options,
    value: props.default_value,
    hint: props.hint,
    required: props.required
}

const model = defineModel<string>()

model.value = props.default_value ?? ''
</script>

<template>
    <div class="mb-3">
        <label :for="id">{{ label }} <span v-if="required" class="text-red-500 text-sm">*</span></label>
        <slot name="input" v-bind="inputSlotPass">
            <InputText :type="field_type?? 'text'" v-model="model" :required="required" :id :invalid fluid :name />
        </slot>
        <Message variant="simple" severity="error" v-if="invalid || hint"> {{ error ?? hint }}</Message>
    </div>
</template>
