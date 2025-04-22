<script setup lang="ts">
import { modals, useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FilterModes, useSelectedResources } from '@/store';
import { Badge, Button, Column, DataTable, IconField, InputIcon, InputText, Menu, MenuMethods } from 'primevue';
import { onMounted, ref, useTemplateRef, watch } from 'vue';

const props = defineProps<{departments:[]}>();

const filters = ref({
    global: {
        value: null,
        matchMode: FilterModes.CONTAINS
    },
    name: {
        value: null,
        matchMode: FilterModes.CONTAINS
    },
    category: {
        value: null,
        matchMode: FilterModes.CONTAINS
    },
    description: {
        value: null,
        matchMode: FilterModes.CONTAINS
    }
});
const globalFilterFields = ['name', 'category', 'description'];

const { selectedResourceIds, selectedResources } = useSelectedResources();

const { deleteResource } = useDeleteResource()

const menuRefs = ref<(MenuMethods| null)[]>([]);
</script>

<template>
    <AuthenticatedLayout title="Departments" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Departments'}]" :buttons="[{label:'Add Department', icon:'ti ti-plus-circle', onClick:()=>{modals.open('department')}}, {label:'Delete', icon:'ti ti-trash', onClick:()=>{deleteResource('department', selectedResourceIds)}, severity:'danger', class: !selectedResourceIds.length? 'hidden':''}]">
        <DataTable v-model:selection="selectedResources" :filters="filters" :globalFilterFields="globalFilterFields" :value="departments" dataKey="id" :paginator="true" :rows="10" :rowsPerPageOptions="[5, 10, 25]" :showGridlines="true" :selectionMode="'multiple'">
            <Column selection-mode="multiple" />
            <Column header="S/N" >
              <template #body="slotProps">
                {{ slotProps.index + 1 }}
              </template>
            </Column>
            <Column header="Name" sortable field="name" />
            <Column header="Category" sortable field="category" />
            <Column header="Description" >
              <template #body="slotProps">
                <div class="line-clamp-2" :title="slotProps.data.description">
                    {{ slotProps.data.description }}
                </div>
              </template>
            </Column>
            <Column header="Positions">
              <template #body="slotProps">
                <Badge severity="info" :value="slotProps.data.roles.length" />
              </template>
            </Column>
            <Column header="Action" >
              <template #body="slotProps">
                <div class="flex gap-2">
                    <Button icon="pi pi-pencil" @click="modals.open('department', {resource_data:slotProps.data})" severity="info" size="small" rounded />
                    <Button icon="pi pi-trash" @click="deleteResource('department', [slotProps.data.id])" rounded severity="danger" size="small" />
                    <Button severity="secondary" icon="ti ti-dots" size="small" rounded @click="menuRefs[slotProps.index]?.toggle($event)" />
                    <Menu popup :ref="(el) => menuRefs[slotProps.index] = (el as unknown as MenuMethods)" :model="[{label: 'Assign Positions', icon:'ti ti-users', command: () => modals.open('department-role', {department:slotProps.data})},{label: 'Assign HoD', icon: 'ti ti-user'}]" />
                </div>
              </template>
            </Column>
            <template #header>
                <div class="flex justify-between">
                    <h5 class="m-0">Departments</h5>
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText v-model="filters.global.value" placeholder="Search..." />
                    </IconField>
                </div>
            </template>
        </DataTable>
    </AuthenticatedLayout>
</template>
