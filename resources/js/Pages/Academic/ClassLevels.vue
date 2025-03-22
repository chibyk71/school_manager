<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import ModalWrapper from '@/Components/Modals/ModalWrapper.vue';
import { fetchSelectOptionsFromDB, modals, openEditModal, useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FilterModes } from '@/store';
import { useForm } from '@inertiajs/vue3';
import { Button, Card, Column, DataTable, Dialog, IconField, InputIcon, InputText, Select, Textarea } from 'primevue';
import { computed, onMounted, ref } from 'vue';

defineProps<{
    classLevels: []
}>()

const selectedClassLevels = ref([]),
    selectedClassLevelIds = computed(() => selectedClassLevels.value.map((classLevel: any) => classLevel.id)),
    schoolsections = ref([]);

    onMounted(async ()=> {
        schoolsections.value = await fetchSelectOptionsFromDB('school-section');
    });

const form = useForm<{
    [x:string]: any;
    name: string;
    display_name: string;
    description: string;
    school_section_id: string;
}>({
    name: '',
    display_name: '',
    description: '',
    school_section_id: ''
});

const filters = ref({
    global: { value: null, matchMode: FilterModes.CONTAINS },
})

const { deleteResource } = useDeleteResource();
</script>

<template>
    <AuthenticatedLayout title="Class Levels"
        :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Class Levels' }]"
        :buttons="[{ label: 'Add Class Level', icon: 'ti ti-school', onClick:()=> modals.open('class-level') }, {label: 'Delete Selected', icon:'ti ti-trash', severity:'danger', onClick: ()=> deleteResource('class-level', selectedClassLevelIds), class: !selectedClassLevelIds.length? 'hidden':''}]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable :filters="filters" v-model:selection="selectedClassLevels" :value="classLevels"
                    :global-filter-fields="['name, dislay_name', 'school_section.name']">
                    <Column selection-mode="multiple" />
                    <Column header="ID" field="id" sortable />
                    <Column header="Name" field="name" sortable></Column>
                    <Column header="Display Name" field="display_name" sortable />
                    <Column header="School Section" field="school_section.name" sortable></Column>
                    <Column header="Description" field="description" />
                    <Column header="Action">
                        <template #body="slotProps">
                            <div class="flex items-center gap-x-3">
                                <Button @click="openEditModal(slotProps.data,form, 'class-level')" severity="secondary" icon="ti ti-edit" class="p-button-sm" />
                                <Button @click="deleteResource('class-level',[slotProps.data.id])" severity="danger" icon="ti ti-trash" class="p-button-sm" />
                            </div>
                        </template>
                    </Column>

                    <template #header>
                        <div class="flex items-center justify-end ">
                            <IconField>
                                <InputIcon>
                                    <i class="ti ti-search"></i>
                                </InputIcon>
                                <InputText v-model="filters.global.value" placeholder="search class levels" />
                            </IconField>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>

    <ModalWrapper id="class-level" resource="class-level" header="create Class Level" :form="form" modal>
        <InputWrapper required label="Name" v-model="form.name" :error="form.errors.name" name="name" field_type="text" />
        <InputWrapper label="Display Name" v-model="form.display_name" :error="form.errors.display_name" name="display_name" field_type="text" />

        <InputWrapper label="School Section" :error="form.errors.school_section_id" name="school_section_id" field_type="text">
            <template #input="{invalid}">
                <Select option-label="name" v-model="form.school_section_id" option-value="id" :invalid :options="schoolsections" fluid />
            </template>
        </InputWrapper>

        <InputWrapper :error="form.errors.description" label="Description" name="description" field_type="text">
            <template #input="{invalid}">
                <Textarea v-model="form.description" :invalid fluid  rows="3"/>
            </template>
        </InputWrapper>
    </ModalWrapper>
</template>
