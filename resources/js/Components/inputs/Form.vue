<!-- SmartForm.vue -->
<template>
    <form @submit.prevent="handleSubmit">
        <SmartInput v-for="field in fields" :key="field.name" :field="field" v-model="form[field.name]"
            :error="form.errors[field.name]" />

        <div class="mt-4">
            <Button type="submit" label="Submit" :loading="form.processing" />
        </div>
    </form>
</template>

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import SmartInput from "./smartInput.vue";
import { Button } from "primevue";
import type { Field } from "@/types";

const props = defineProps<{
    fields: Field[];
    submitUrl: string;
    method?: "post" | "put" | "patch";
    initialValues?: Record<string, any>;
}>();

const initialData = props.fields.reduce((acc, field) => {
    acc[field.name] =
        props.initialValues?.[field.name] ??
        field.default_value ??
        (field.has_options ? null : "");
    return acc;
}, {} as Record<string, any>);

const form = useForm(initialData);

const handleSubmit = () => {
    form.clearErrors();
    if (props.method === "put" || props.method === "patch") {
        form[props.method](props.submitUrl);
    } else {
        form.post(props.submitUrl);
    }
};
</script>
