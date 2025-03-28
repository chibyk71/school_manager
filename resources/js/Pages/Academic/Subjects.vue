<script setup lang="ts">
import CustomSelect from '@/Components/inputs/customSelect.vue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import ModalWrapper from '@/Components/Modals/ModalWrapper.vue';
import { modals } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Card, Column, DataTable, Select, Textarea } from 'primevue';

const form = useForm({
    name: '',
    code: '',
    is_elective: false,
    school_section: '',
    status: '',
    description: '',
})
</script>

<template>
    <AuthenticatedLayout title="Subjects" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Subjects' }]" :buttons="[{ label: 'Add Subjects', icon: 'ti ti-school', onClick: () => modals.open('subjects') }]">

        <!-- Guardians List -->
        <Card>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Code"></Column>
                    <Column header="Type">
                      <template #body="slotProps">
                        <span v-if="slotProps.data.is_elective">Elective</span>
                        <span v-else>Core</span>
                      </template>
                    </Column>
                    <Column header="School Section"></Column>
                    <Column header="Status"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
    <ModalWrapper :form id="subjects" header="Add Subjects" resource="subject" modal>
        <InputWrapper label="Name" v-model="form.name" :error="form.errors.name" required name="name" field_type="text"></InputWrapper>
        <InputWrapper label="Code" v-model="form.code" :error="form.errors.code" required name="code" field_type="text"></InputWrapper>
        <InputWrapper label="Type" :error="form.errors.is_elective" required name="type" field_type="select">
            <template #input="data">
                <Select fluid v-bind="data" :options="[{label:'Core',value:false},{label:'Elective',value:true}]" option-value="value" option-label="label" v-model="form.is_elective" />
            </template>
        </InputWrapper>
        <InputWrapper label="School Section" v-model="form.school_section" :error="form.errors.school_section" required name="school_section" field_type="text">
            <template #input="{invalid}">
                <CustomSelect multiple :invalid resource="school-section" v-model="form.school_section" />
            </template>
        </InputWrapper>
        <InputWrapper label="Description" v-model="form.description" :error="form.errors.description" name="description" field_type="textarea">
            <template #input="{invalid, id, name}">
                <Textarea fluid v-model="form.description" :name :invalid :id rows="3"></Textarea>
            </template>
        </InputWrapper>
    </ModalWrapper>
</template>
