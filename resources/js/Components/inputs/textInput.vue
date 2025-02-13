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
</script>

<template>
    <div class="mb-3">
        <label>{{ label }}</label>
        <IconField>
            <InputIcon :class="icon"></InputIcon>
            <slot name="input" :invalid="!!error" >
                <InputText :name :invalid="!!error" v-model="model" required :type fluid />
            </slot>
        </IconField>
        <Message severity="error" variant="simple" v-if="error"> {{ error }} </Message>
    </div>
</template>
