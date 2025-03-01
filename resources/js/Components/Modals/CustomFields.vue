<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import CustomInput from '../inputs/CustomInput.vue';
import { useToast } from 'primevue';
import { Field } from '@/types';
import { InputHTMLAttributes, InputTypeHTMLAttribute } from 'vue';

    defineProps<{
        fields: Field[],
        resource: string
    }>()

    const toast = useToast();

    const form = useForm<{
        resource: string,
        fields: ({
            icon?: string,
            error?: string,
        } & Field & (InputHTMLAttributes & { type: InputTypeHTMLAttribute | 'select'|'textarea', required?: boolean }))[]
    }>({
        resource: '',
        fields: [{
            name: '',
            label: '',
            field_type: '',
            icon: '',
            error: '',
            type: 'text',
            required: false
        }]
    })

    const submit = () => {
        form.post(route('custom-fields.store'),{
            preserveState: true,
            onSuccess: () => {
                toast.add({severity: 'success', summary: 'Success', detail: 'Custom fields saved successfully'})
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Error', detail: 'Custom fields could not be saved'})
            }
        })
    }
</script>

<template>
    <form action="" method="post">
        <CustomInput v-model="form.fields[index]" v-for="(field, index) in fields" v-bind="field" />
    </form>
</template>
