<!-- this component is used to create a custom field of edit an existing one -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import CustomInput from '../inputs/CustomInput.vue';
import { Button, Checkbox, InputChips, Select, ToggleSwitch, useToast } from 'primevue';
import { Field } from '@/types';
import InputWrapper from '../inputs/InputWrapper.vue';
import { computed, inject, ref } from 'vue';
import { useSubmitForm } from '@/helpers';

const dialogRef = inject<{ value: { close: () => void, data: any } }>("dialogRef");

const props = ref<{
    field?: Field & { required?: boolean },
    resources: Array<string>,
    id:  string| number| undefined
}>({
    field: dialogRef?.value.data.field ?? [],
    resources: dialogRef?.value.data.resources,
    id: dialogRef?.value.data.id
});

const closeModal = () => {
    dialogRef?.value.close();
}

const form = useForm<Field & { required?: boolean, resource: string }>({
    resource: '',
    field_type: props.value.field?.field_type ?? 'text', // Specifies the type of input.
    required: props.value.field?.required ?? false,
    name: props.value.field?.name ?? '', // The unique identifier for the custom field.
    label: props.value.field?.label ?? '', // The human-readable label for the field.
    placeholder: props.value.field?.placeholder ?? '', // Placeholder text for input fields.
    classes: props.value.field?.classes ?? '', // Additional CSS classes for styling.
    options: props.value.field?.options ?? [], // Available options for select, radio, or checkbox fields.
    default_value: props.value.field?.default_value ?? null, // Default value for the field.
    description: props.value.field?.description ?? '', // Longer description for the field.
    hint: props.value.field?.hint ?? '', // Tooltip or hint for the field.
    category: props.value.field?.category ?? '', // Grouping of fields into categories.
});

const optionable = ref(['select', 'radio', 'checkbox']);
    const hasOptions = computed(() => {
        return optionable.value.includes(form.field_type);
    });

const { submitForm } = useSubmitForm()

const submit = () => {
    submitForm(form, 'custom-field', props.value.id, {
        onSuccess: (prop) => {
            dialogRef?.value.close()
        }
    })
}
</script>

<template>
    <PerfectScrollbar>
        <form action="" method="post" @submit.prevent="submit" class="w-[100vw] h-full sm:w-[50vw] overflow-hidden relative pb-5">
            <InputWrapper label="Resource" name="resource" field_type="select" required>
                <template #input="slotProps">
                    <Select fluid v-model="form.resource" v-bind="slotProps" :options="props.resources" />
                </template>
            </InputWrapper>
            <InputWrapper required label="Field Label" field_type="text" v-model="form.label" name='label'
                hint="The human-readable label for the field" :error="form.errors.label" />

            <InputWrapper label="Field Name" field_type="text" v-model="form.name" name='name'
                hint="The unique identifier for the custom field" :error="form.errors.name" />

            <InputWrapper required label="Field Type" field_type="select" name='field_type' hint="Specifies the type of input">
                <template #input="data">
                    <Select v-model="form.field_type" fluid v-bind="data"
                        :options="['text', 'number', 'select', 'radio', 'checkbox', 'email']">
                    </Select>
                </template>
            </InputWrapper>

            <template v-if="hasOptions">
                <!-- add options incase the field type is select or check box  or radio -->
                 <!-- TODO: change to another input -->
                <InputWrapper field_type="text" label="Options Label" name='options' :error="form.errors.options" hint="Available options for select, radio, or checkbox fields" required>
                    <template #input="data">
                        <InputChips :pt="{input: {class: 'flex items-center gap-2 overflow-x-scroll'}, inputItem: {
                            class: 'block w-full'
                        }}" :allow-duplicate="false" v-bind="data" v-model="form.options"  />
                    </template>
                </InputWrapper>
            </template>

            <InputWrapper field_type="text" label="PlaceHolder" v-model="form.placeholder" name='placeholder'
                :error="form.errors.placeholder" hint="Placeholder text for input fields."></InputWrapper>

            <InputWrapper field_type="text" label="Description" v-model="form.description" name='description'
                :error="form.errors.description" hint="Longer description for the field" />

            <InputWrapper field_type="text" label="Hint" v-model="form.hint" name='hint' :error="form.errors.hint"
                hint="Tooltip or hint for the field" />

            <InputWrapper field_type="text" label="Category" v-model="form.category" name='category'
                :error="form.errors.category" hint="Grouping of fields into categories" />

            <InputWrapper field_type="text" label="Default Value" v-model="form.default_value" name='default_value'
                :error="form.errors.default_value" hint="Default value for the field" />

            <div class="flex items-center justify-between mb-4 mr-3">
                <div class="status-title">
                    <h5>Required</h5>
                    <p>Make the field required by toggle </p>
                </div>
                <ToggleSwitch id="required" name="required" class="self-end" binary v-model="form.required" />
            </div>

            <div class="flex justify-end gap-x-3">
                <Button label="Cancel" @click="closeModal()" severity="secondary" />
                <Button type="submit" label="Save" :loading="form.processing" />
            </div>
        </form>
    </PerfectScrollbar>
</template>

<style>
.ps {
    max-height: 80vh;
}
</style>
