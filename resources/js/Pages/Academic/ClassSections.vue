<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import ModalWrapper from '@/Components/Modals/ModalWrapper.vue';
import { fetchSelectOptionsFromDB, modals, openEditModal, useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useSelectedResources } from '@/store';
import { useForm } from '@inertiajs/vue3';
import { Badge, Button, Card, Column, DataTable, Select } from 'primevue';
import { computed, onMounted, ref, watch } from 'vue';

const {classLevel} = defineProps<{
    classSections: [],
    classLevel?: {id:number,display_name:string},
}>()

const form = useForm({
    name: '',
    capacity: null,
    room: '',
    class_level_id: classLevel?.id,
    status: 'active',
})

const classLevels = ref([]);

onMounted(async ()=> {
    classLevels.value = await fetchSelectOptionsFromDB('class-level');
})

const { selectedResourceIds, selectedResources } = useSelectedResources();

const { deleteResource } = useDeleteResource()
    

</script>

<template>
    <AuthenticatedLayout title="Class Sectionss"
        :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Class Sections' }]"
        :buttons="[{ label: 'Add Sections', icon: 'ti ti-school', onClick: ()=> modals.open('class-section')}, { label: 'Import Sections', severity:'secondary', icon: 'ti ti-import' },{label: 'Delete selected', icon: 'ti ti-trash', severity:'danger', class: selectedResourceIds.length < 1? 'hidden': '', onClick: ()=> deleteResource('class-section', selectedResourceIds)}]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable v-model:selection="selectedResources" :value="classSections" :paginator="true" :rows="10" :rowsPerPageOptions="[5,10,20]" :selectionKeys="selectedResourceIds">
                    <Column selection-mode="multiple" />
                    <Column header="S/N">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column header="Name" field="name">
                    </Column>
                    <Column header="Class Level" field="class-level.dislay_name" />
                    <Column header="Capacity" field="capacity"></Column>
                    <Column header="No. of Students" field="no_of_students">
                      <template #body="slotProps">
                        {{slotProps.data.no_of_students}}
                      </template>
                    </Column>
                    <Column header="Status">
                      <template #body="slotProps">
                        <Badge :value="slotProps.data.status" :severity="slotProps.data.status == 'active' ? 'success' : 'danger'" />
                      </template>
                    </Column>
                    <Column header="Action" >
                      <template #body="slotProps">
                        <Button icon="pi pi-pencil" @click="()=>openEditModal(slotProps.data, form, 'class-section')" size="small" severity="secondary" class="p-button-rounded mr-2" />
                        <Button severity="danger" @click="()=>deleteResource('class-section', [slotProps.data.id])" icon="pi pi-trash" class="p-button-rounded p-button-danger" />
                      </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>

    <ModalWrapper :form id="class-section" header="" resource="class-section">
        <InputWrapper field_type="text" name="name" label="Name" v-model="form.name" :error="form.errors.name" />
        <InputWrapper field_type="number" name="capacity" label="Capacity" v-model="form.capacity" :error="form.errors.capacity"  />
        <InputWrapper field_type="text" name="room" label="Room No." v-model="form.room" :error="form.errors.room" />
        <InputWrapper field_type="select" name="class_level_id" label="Class Level" :error="form.errors.class_level_id" required>
            <template #input="{invalid}">
                <Select fluid :invalid :options="classLevels" option-label="name" option-value="id" v-model="form.class_level_id" />
            </template>
        </InputWrapper>
        <InputWrapper :error="form.errors.status" field_type="select" name="status" label="Status">
            <template #input="{ invalid }">
                <Select fluid :invalid v-model="form.status" option-value="value" option-label="label" :options="[{label:'Active', value:'active'},{label:'Inactive', value:'inactive'}]" />
            </template>
        </InputWrapper>
    </ModalWrapper>
</template>
