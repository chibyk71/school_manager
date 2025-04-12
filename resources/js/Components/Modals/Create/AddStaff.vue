<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ModalWrapper from '../ModalWrapper.vue';
import ResourceCard from './Partial/ResourceCard.vue';
import { Button, Password, ProgressSpinner } from 'primevue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import { Field } from '@/types';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import CustomInput from '@/Components/inputs/CustomInput.vue';

const form = useForm<{
    email: string;
    f_name: string;
    m_name: string;
    l_name: string;
    enrollment_id: string;
    password: string;
    confirmpassword: string;
    customFields: Record<string, string>;
}>({
    email: '',
    f_name: '',
    m_name: '',
    l_name: '',
    enrollment_id: '',
    password: '',
    confirmpassword: '',
    customFields: {},
});

// Dynamically populate customFields based on the customField data
onMounted(async () => {
    loading.value = true;
    customField.value = await axios.get<Array<CustomField>>('custom-field/staff/json')
        .then(({ data }) => {
            loading.value = false;

            // Initialize customFields with keys for each field and empty values
            data.forEach(({ fields }) => {
                fields.forEach(({ name }) => {
                    form.customFields[name] = '';
                });
            });

            return data;
        });
});

type CustomField = {category: string, count:number, fields: Array<Field>}

const customField = ref<Array<CustomField>>([]),
    loading = ref(false);

onMounted(async ()=> {
    loading.value = true
    customField.value = await axios.get<Array<CustomField>>('custom-field/staff/json')
        .then(({data})=> {
            loading.value = false;
            return data
        })
})
</script>

<template>
    <ModalWrapper :loading :form="form" id="add-staff" resource="staff" header="Add Staff" class="!w-[80vw] relative">
        <form v-if="!loading" action="staffs.html">

            <ResourceCard header="Authentication & Identification" icon="ti ti-password-user" >
                <div class="flex items-center gap-x-3 mb-3">
                    <div class="flex items-center justify-center p-avatar size-20 border border-dashed mr-2 flex-shrink-0 text-dark frames">
                        <i class="ti ti-photo-plus text-normal size-4"></i>
                    </div>
                    <div class="profile-upload">
                        <div class="profile-uploader flex items-center gap-x-3">
                            <Button outlined severity="secondary" size="small" class="border-dashed">
                                Upload
                                <input type="file" class="opacity-0 absolute top-0 left-0 size-full" multiple>
                            </Button>
                            <Button label="Remove" size="small" />
                        </div>
                        <p class="text-xs/none font-light mt-0.5">Upload image size 4MB, Format JPG, PNG, SVG</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <InputWrapper label="Email" field_type="email" name="email" />
                    <InputWrapper label="Employment Id" field_type="text" name="enrollment_id" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                    <InputWrapper label="First Name" field_type="text" name="f_name" />
                    <InputWrapper label="Middle Name" field_type="text" name="m_name" />
                    <InputWrapper label="Last Name" field_type="text" name="l_name" />
                </div>
            </ResourceCard>

            <ResourceCard :header="category" icon="ti ti-info-square-rounded" v-for="{category, fields, count} in customField">
                <div :class="`grid grid-cols-1 md:grid-cols-${count} gap-4`">
                    <CustomInput v-bind="field" v-for="field in fields" ;
                    v-model="form.customFields[field.name]" :error="form.errors[`customFields.${field.name}` as keyof typeof form.er]" :key="field.name" :label="field.label" :field_type="field.field_type" :name="field.name" />
                </div>
            </ResourceCard>

            <!-- Bank Details -->
             <ResourceCard header="Bank Account Details" icon="ti ti-bank">
                <div class="grid lg:grid-cols-3 md:grid-cols-2 gap-4">
                    <InputWrapper required field_type="text" label="Account Name" name="acct_name" />
                    <InputWrapper required field_type="text" label="Account Number" name="acct_number" />
                    <InputWrapper required field_type="text" label="Bank Name" name="bank_name" />
                    <InputWrapper field_type="text" label="Branch Name" name="branch_name" />
                </div>
             </ResourceCard>
            <!-- /Bank Details -->

            <!-- Social Media Links -->
             <ResourceCard header="Social Media Links" icon='ti ti-building'>
                <div class="grid md:grid-cols-2 gap-x-4">
                    <InputWrapper label="Facebook URL" field_type="text" name='facebook' />
                    <InputWrapper label="Twitter URL" field_type="text" name='twitter' />
                    <InputWrapper label="Linkedin URL" field_type="text" name='linkedin' />
                    <InputWrapper label="Instagram URL" field_type="text" name='instagram' />
                </div>
             </ResourceCard>
            <!-- /Social Media Links -->

            <!-- Password -->
             <ResourceCard header="Password" icon="ti ti-lock">
                <div class="grid md:grid-cols-2 gap-x-4">
                    <InputWrapper label='Password' name='password' field_type="password">
                        <Password :feedback="false" toggle-mask />
                    </InputWrapper>
                    <InputWrapper label='Confirm Password' name='confirmpassword' field_type="password">
                        <Password :feedback="false" toggle-mask />
                    </InputWrapper>
                </div>
             </ResourceCard>
            <!-- /Password -->

        </form>
    </ModalWrapper>
</template>
