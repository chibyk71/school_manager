<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import ModalWrapper from '@/Components/Modals/ModalWrapper.vue';
import { modals, openEditModal, useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FilterModes } from '@/store';
import { useForm } from '@inertiajs/vue3';
import { Badge, Button, Card, Checkbox, Column, DataTable, DatePicker, Menu, ToggleSwitch, useDialog } from 'primevue';
import { ref } from 'vue';
import SettingsRow from '../Settings/Partials/SettingsRow.vue';
import Semesters from '@/Components/Modals/Show/Semesters.vue';

defineProps<{
    academicSessions: any[];
}>()
const filters = ref({global: { value: null, matchMode: FilterModes.CONTAINS }});
const moreMenu = ref<any[]>([]);

const form = useForm<{
    name: string;
    start_date: Date | Date[] | (Date | null)[] | null | undefined;
    end_date: Date | Date[] | (Date | null)[] | null | undefined;
    is_current: boolean;
}>({
    name: '',
    start_date: null,
    end_date: null,
    is_current: false
});

const { deleteResource } = useDeleteResource();

const dialog = useDialog();

</script>

<template>
    <AuthenticatedLayout title="Academic Session"
        :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Academic Session' }]"
        :buttons="[{ label: 'Add Session', icon: 'ti ti-plus', onClick: ()=> modals.open('academic-session') }]">

        <Card>
            <template #content>
                <DataTable v-model:filters="filters" :value="academicSessions" :paginator="true" :rows="10" :rowsPerPageOptions="[5,10,20]" :globalFilterFields="['name']">
                    <Column selection-mode="multiple" />
                    <Column header="ID" field="id" />
                    <Column header="Name" field="name" />
                    <Column header="Start Date" field="start_date_human" />
                    <Column header="End Date" field="end_date_human" >
                    </Column>
                    <Column header="Current">
                        <template #body="slotProps">
                            <Badge :value="slotProps.data.is_current" :severity="slotProps.data.is_current ? 'success' : 'danger'" />
                        </template>
                    </Column>
                    <Column header="Action" >
                      <template #body="slotProps">
                        <div class="flex items-center gap-x-2">
                            <Button @click="openEditModal(slotProps.data,form, 'academic-session')" icon="ti ti-edit" size="small" />
                            <Button @click="deleteResource('academic-session', [slotProps.data.id])" icon="ti ti-trash" size="small" severity="danger" />
                            <Button icon="ti ti-dots" severity="secondary" size="small" @click="(e)=>moreMenu[slotProps.index]?.toggle(e)" />
                            <Menu popup :model="[{label:'View Semesters', command: () => dialog.open(Semesters, { data: {session: slotProps.data.name, session_id: slotProps.data.id}, props: {header: 'Semesters', maximizable: true} }) },]" :ref="(e) => moreMenu[slotProps.index] = e" />
                        </div>
                      </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <ModalWrapper id="academic-session" resource="academic-session" header="create Academic Session" :form="form" modal>
            <InputWrapper required label="Name" v-model="form.name" :error="form.errors.name" name="name" field_type="text" />
            <InputWrapper required label="Start Date" :error="form.errors.start_date" name="start_date" field_type="date">
                <template #input="slotProps">
                    <DatePicker date-format="yy-mm-dd" :invalid="slotProps.invalid" fluid v-model="form.start_date" :showIcon="true" />
                </template>
            </InputWrapper>
            <InputWrapper required label="End Date" :error="form.errors.end_date" name="end_date" field_type="date">
                <template #input="slotProps">
                    <DatePicker date-format="yy-mm-dd" :invalid="slotProps.invalid" fluid v-model="form.end_date" :showIcon="true" />
                </template>
            </InputWrapper>
            <SettingsRow label="Is Current" description="Set Session to current">
                <ToggleSwitch v-model="form.is_current" name="is_current" />
            </SettingsRow>
        </ModalWrapper>
    </AuthenticatedLayout>
</template>
