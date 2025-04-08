<!-- this component is used to create a custom field of edit an existing one -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { InputChips, Select, ToggleSwitch } from 'primevue';
import { Field } from '@/types';
import InputWrapper from '../../inputs/InputWrapper.vue';
import { computed, inject, ref } from 'vue';
import ModalWrapper from '../ModalWrapper.vue';

const props = defineProps<{
    resource_data?: Field & { required?: boolean },
    resources: Array<string>,
    resource_id?: string | number,
}>();

const form = useForm<Field & { required?: boolean, model_type: string }>({
    model_type: '',
    field_type: props.resource_data?.field_type ?? 'text', // Specifies the type of input.
    required: props.resource_data?.required ?? false,
    name: props.resource_data?.name ?? '', // The unique identifier for the custom field.
    label: props.resource_data?.label ?? '', // The human-readable label for the field.
    placeholder: props.resource_data?.placeholder ?? '', // Placeholder text for input fields.
    classes: props.resource_data?.classes ?? '', // Additional CSS classes for styling.
    options: props.resource_data?.options ?? [], // Available options for select, radio, or checkbox fields.
    default_value: props.resource_data?.default_value ?? null, // Default value for the field.
    description: props.resource_data?.description ?? '', // Longer description for the field.
    hint: props.resource_data?.hint ?? '', // Tooltip or hint for the field.
    category: props.resource_data?.category ?? '', // Grouping of fields into categories.
});

const resources: Array<string> = (inject('data') as any)?.value?.resources ?? props.resources ?? [];

const optionable = ref(['select', 'radio', 'checkbox']);
const hasOptions = computed(() => {
    return optionable.value.includes(form.field_type);
});

</script>

<template>
    <ModalWrapper :form id="custom-field" resource="custom-field" header="Create Custom Field">
        <PerfectScrollbar>
            <form action="" method="post" class="h-full overflow-hidden relative mb-5">
                <InputWrapper label="Resource" name="resource" field_type="select" required>
                    <template #input="slotProps">
                        <Select fluid v-model="form.model_type" v-bind="slotProps" :options="resources" />
                    </template>
                </InputWrapper>
                <InputWrapper required label="Field Label" field_type="text" v-model="form.label" name='label'
                    hint="The human-readable label for the field" :error="form.errors.label" />

                <InputWrapper label="Field Name" field_type="text" v-model="form.name" name='name'
                    hint="The unique identifier for the custom field" :error="form.errors.name" />

                <InputWrapper required label="Field Type" field_type="select" name='field_type'
                    hint="Specifies the type of input">
                    <template #input="data">
                        <Select v-model="form.field_type" fluid v-bind="data"
                            :options="['text', 'number', 'select', 'radio', 'checkbox', 'email']">
                        </Select>
                    </template>
                </InputWrapper>

                <template v-if="hasOptions">
                    <!-- add options incase the field type is select or check box  or radio -->
                    <!-- TODO: change to another input -->
                    <InputWrapper field_type="text" label="Options Label" name='options' :error="form.errors.options"
                        hint="Available options for select, radio, or checkbox fields" required>
                        <template #input="data">
                            <InputChips :pt="{
                                input: { class: 'flex items-center gap-2 overflow-x-scroll' }, inputItem: {
                                    class: 'block w-full'
                                }
                            }" :allow-duplicate="false" v-bind="data" v-model="form.options" />
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

                <div class="flex items-center justify-between mb-10 mr-3">
                    <div class="status-title">
                        <h5>Required</h5>
                        <p>Make the field required by toggle </p>
                    </div>
                    <ToggleSwitch id="required" name="required" class="self-end" v-model="form.required" />
                </div>
            </form>
        </PerfectScrollbar>
    </ModalWrapper>
</template>

<style>
.ps {
    max-height: 75vh;
}
</style>
