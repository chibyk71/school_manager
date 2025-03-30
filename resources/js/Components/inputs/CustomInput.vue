<script setup lang="ts">
import { type Field } from '@/types';
import { Checkbox, InputText, Password, RadioButton, Select, Textarea } from 'primevue';
import { computed, type InputHTMLAttributes, type InputTypeHTMLAttribute } from 'vue';
import InputWrapper from './InputWrapper.vue';

const props = defineProps<{
    icon?: string,
    error?: string,
}& Field & (InputHTMLAttributes & { type: InputTypeHTMLAttribute|'select'|'textarea', required?: boolean })>()

const model = defineModel<any>();

const type = computed(()=> props.field_type ?? props.type)
</script>

<template>
    <InputWrapper :label :name :error :field_type :hint :placeholder>
        <template #input="slotProps">
            <Password v-if="type === 'password'" v-bind="slotProps" :feedback="false" toggle-mask fluid v-model="model" />

            <Select v-model="model" v-else-if="type === 'select'" option-label="label" option-value="value" v-bind="slotProps" fluid/>

            <Textarea v-model="model" v-else-if="type === 'textarea'" v-bind="slotProps" fluid rows="3" />

            <div class="flex items-center gap-4 flex-wrap" v-else-if="type === 'checkbox' || type === 'radio'">
                <div class="flex item-center gap-3" v-for="({label, value}, index) in slotProps.options">
                    <RadioButton v-if="type === 'radio'" :key="value" :value :name="slotProps.name" v-model="model" />
                    <Checkbox v-else  binary :value :name="slotProps.name" :id="`${slotProps.id}-${index}`" v-model="model" />
                    <label :for="`${slotProps.id}-${index}`">{{ label }}</label>
                </div>
            </div>

            <InputText v-else v-bind="slotProps" fluid v-model="model" />
        </template>

    </InputWrapper>

</template>
