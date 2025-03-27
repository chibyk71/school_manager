<template>
    <DataTable :value="terms" :rows="10" v-model:selection="selectedResources" >
        <template #header>
            <div class="flex items-center flex-col-reverse">
                <h4 class="text-left w-full">Terms/Semester for {{ data.session }} Academic Session</h4>
                <div class="flex items-center gap-x-3 justify-end w-full">
                    <Button v-if="selectedResourceIds.length > 0" @click="()=> deleteResource('term', selectedResourceIds)" icon="pi pi-trash" label="Delete Selected" severity="danger" size='small' />
                    <Button icon="pi pi-plus" label="Add Semester" size="small" class="" @click="modals.open('term')" />
                </div>
            </div>
        </template>
        <Column selection-mode="multiple" />
        <Column field="id" header="ID" />
        <Column field="name" header="Name" />
        <Column field="start_date_human" header="Start Date" />
        <Column field="end_date_human" header="End Date" />
        <Column header="Status">
          <template #body="slotProps">
            <span :class="{
              'text-green-500': slotProps.data.status === 'active',
              'text-yellow-500': slotProps.data.status === 'pending',
              'text-red-500': slotProps.data.status === 'completed'
            }">{{ slotProps.data.status }}</span>
          </template>
        </Column>
        <Column header="Action">
            <template #body="slotProps">
                <div class="flex items-center gap-x-2">
                    <Button icon="pi pi-pencil" @click="()=> openEditModal(slotProps.data, form, 'term')" size="small" severity="secondary" />
                    <Button @click="()=> deleteResource('term',[slotProps.data.id])" icon="pi pi-trash" size="small" severity="danger" />
                </div>
            </template>
        </Column>
        <template #empty>
            <div class="p-4 text-center">
                <p>No terms/semesters found</p>
            </div>
        </template>
    </DataTable>
    <ModalWrapper @success="() => fetchTerms()" :form id="term" resource="term" header="Create Term/Semester" modal >
        <div class="">
            <InputWrapper required label="Name" v-model="form.name" :error="form.errors.name" name="name" field_type="text" />
            <InputWrapper required label="Start Date" :error="form.errors.start_date" name="start_date" field_type="date">
                <template #input="{invalid, id, name, required}">
                    <DatePicker placeholder="yyyy/mm/dd"  v-model="form.start_date" fluid show-icon icon-display="input" :invalid :id :name :required date-format="yy/mm/dd" />
                </template>
            </InputWrapper>
            <InputWrapper required label="End Date" :error="form.errors.end_date" name="end_date" field_type="date">
                <template #input="{invalid, id, name, required}">
                    <DatePicker placeholder="yyyy/mm/dd" v-model="form.end_date" fluid show-icon icon-display="input" :invalid :id :name :required date-format="yy/mm/dd" />
                </template>
            </InputWrapper>
            <InputWrapper field_type="select" name="status" required label="Status" :error="form.errors.status">
                <template #input>
                    <Select option-label="label" option-value="value" fluid v-model="form.status" :options="[{label: 'Pending', value: 'pending'}, {label: 'Active', value: 'active'}, {label: 'Completed', value: 'completed'}]" />
                </template>
            </InputWrapper>
        </div>
    </ModalWrapper>
</template>

<script setup lang="ts">
import axios from 'axios';
import { Button, Column, DataTable, DatePicker, Select } from 'primevue';
import { inject, onMounted, ref } from 'vue';
import ModalWrapper from '../ModalWrapper.vue';
import { useForm } from '@inertiajs/vue3';
import { modals, openEditModal, useDeleteResource } from '@/helpers';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import { useSelectedResources } from '@/store';
const data = ref<{ session: string }>({ session: '' });

const dialogRef = inject<{ value: { data: { session: string, session_id: number } } }>('dialogRef');

const terms = ref<{ id: number; name: string; start_date: string; end_date: string }[]>([]);

onMounted(() => {
    fetchTerms();
    data.value = dialogRef!.value.data;
})

const form = useForm({
    name: '',
    start_date: null,
    end_date: null,
    status: 'pending',
    academic_session_id: dialogRef!.value.data.session_id
})

function fetchTerms() {
    axios.get(route('term.index', dialogRef!.value.data.session_id))
        .then(response => response.data)
        .then(data => {
            terms.value = data;
        })
        .catch(error => {
            console.error('Error fetching terms:', error);
        });
}

const { selectedResourceIds, selectedResources } = useSelectedResources()

const { deleteResource } = useDeleteResource()
</script>
