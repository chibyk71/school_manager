<script setup lang="ts">
import { Accordion, AccordionTab } from 'primevue';
import DynamicInput from './CustomFieldRenderer.vue';
import type { FieldCategory } from '@/types/custom-fields';

// TODO: add support for nested categories and use in form fields renderer

defineProps<{
    category: FieldCategory;
    model: Record<string, any>;
    errors: Record<string, string>;
}>();
</script>

<template>
    <Accordion :activeIndex="0" :multiple="true">
        <AccordionTab :header="category.label">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <DynamicInput v-for="field in category.fields" :key="field.id" :field="field"
                    v-model="model[field.name]" :error="errors[field.name]" />
            </div>
        </AccordionTab>
    </Accordion>
</template>
