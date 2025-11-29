<!-- resources/js/Components/inputs/textInput.vue -->
<script setup lang="ts">
import { IconField, InputIcon, InputText, Message } from 'primevue';
const model = defineModel<string>();
defineProps<{
    label: string,
    icon?: string,
    type?: string,
    name?: string,
    error?: string
}>()

const id = `text-input-${Math.random().toString(36).substr(2, 9)}`;
</script>

<template>
    <div class="mb-4">
        <label class="font-medium text-color mb-2 inline-block relative" :for='id'>{{ label }}</label>
        <IconField>
            <InputIcon v-if="icon" :class="icon"></InputIcon>
            <slot name="input" :invalid="!!error">
                <InputText :id="id" :name="name" :invalid="!!error" v-model="model" required :type="type" fluid />
            </slot>
        </IconField>
        <Message severity="error" variant="simple" v-if="error"> {{ error }} </Message>
    </div>
</template>
