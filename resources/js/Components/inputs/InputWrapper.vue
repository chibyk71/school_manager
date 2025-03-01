<script setup lang="ts">
import { type Field } from '@/types';
import { InputText, Message } from 'primevue';
import { computed, defineProps } from 'vue';

const props = defineProps<Field & {
    error?: string;
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
}

const model = defineModel({ default: props.default_value ?? ''})
</script>

<template>
    <div class="mb-3">
        <label :for="id">{{ label }}</label>
        <slot name="input" v-bind="inputSlotPass">
            <InputText v-model="model" :id :invalid fluid :name />
        </slot>
        <Message severity="error" v-if="invalid"> {{ error }}</Message>
    </div>
</template>
