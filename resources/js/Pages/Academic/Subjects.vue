<script setup lang="ts">
import CustomSelect from '@/Components/inputs/customSelect.vue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import AssignTeacherSubjectClass from '@/Components/Modals/Create/AssignTeacherSubjectClass.vue';
import ModalWrapper from '@/Components/Modals/ModalWrapper.vue';
import { modals, openEditModal, useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useSelectedResources } from '@/store';
import { useForm } from '@inertiajs/vue3';
import { Badge, Button, Card, Column, DataTable, Select, Textarea, useDialog } from 'primevue';

const form = useForm({
    name: '',
    code: '',
    is_elective: false,
    school_section: '',
    status: '',
    description: '',
})

const props = defineProps({
    subjects: []
})

const { deleteResource } = useDeleteResource(),
    { selectedResourceIds, selectedResources } = useSelectedResources(),
    dialog = useDialog();
</script>

<template>
    <AuthenticatedLayout title="Subjects" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Subjects' }]" :buttons="[{label:'Delete Selected', icon:'ti ti-trash', severity:'danger', onClick: ()=> deleteResource('subject', selectedResourceIds), class: selectedResourceIds.length < 1? 'hidden': ''},{ label: 'Add Subjects', icon: 'ti ti-school', onClick: () => modals.open('subjects') }]">

        <!-- Guardians List -->
        <Card>
            <template #content>
                <DataTable v-model:selection="selectedResources" :value="subjects" :paginator="true" :rows="10" :rowsPerPageOptions="[5, 10, 20, 50]">
                    <Column selection-mode="multiple" />
                    <Column header="ID" >
                      <template #body="slotProps">
                        {{ slotProps.index + 1 }}
                      </template>
                    </Column>
                    <Column header="Name" field="name" sortable>
                      <template #body="slotProps"><span class="capitalize">{{ slotProps.data.name }}</span></template>
                    </Column>
                    <Column header="Code" field="code" sortable></Column>
                    <Column header="Type" sortable>
                      <template #body="slotProps">
                        <span v-if="slotProps.data.is_elective">Elective</span>
                        <span v-else>Core</span>
                      </template>
                    </Column>
                    <Column header="School Section" sortable>
                        <template #body="slotProps">
                            <div class="flex">
                                <Badge v-if="slotProps.data.school_sections.length > 0" severity="secondary" :value="slotProps.data.school_sections[0].display_name" />
                                <template v-if="slotProps.data.school_sections.length > 1">
                                    <span class="text-xs text-gray-500 ml-2">+{{ slotProps.data.school_sections.length - 1 }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-xs text-gray-500 ml-2">No School Section</span>
                                </template>
                            </div>
                        </template>
                    </Column>
                    <Column header="Status">
                      <template #body="slotProps">
                        <Badge v-if="slotProps.data.status == 'active'" severity="success" :value="slotProps.data.status" />
                        <Badge v-else severity="danger" :value="slotProps.data.status" />
                      </template>
                    </Column>
                    <Column header="Action" >
                      <template #body="slotProps">
                        <div class="flex items-center gap-x-2">
                            <Button icon="ti ti-trash" @click="deleteResource('subject', [slotProps.data.id])" severity="danger" />
                            <Button icon="ti ti-edit" @click="openEditModal(slotProps.data, form, 'subjects')" />
                            <!-- assign teacher and class to subject -->
                            <Button v-tooltip="'Assign Teacher and Class'" icon="ti ti-users" @click="dialog.open(AssignTeacherSubjectClass, { data: {subject_id: slotProps.data.id},props: {modal:true, header: 'Assign Teacher and Class', maximizable: true,} })" />
                        </div>
                      </template>
                    </Column>
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
