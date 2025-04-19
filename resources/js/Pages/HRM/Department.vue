<script setup lang="ts">
import { useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FilterModes, useSelectedResources } from '@/store';
import { Badge, Button, Column, DataTable, IconField, InputIcon, InputText } from 'primevue';
import { ref } from 'vue';

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

</script>

<template>
    <AuthenticatedLayout title="Departments" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Departments'}]" :buttons="[{label:'Add Department', icon:'ti ti-plus-circle'}, {label:'Delete', icon:'ti ti-trash', onClick:()=>{deleteResource('department', selectedResourceIds)}, severity:'danger', class: !selectedResourceIds.length? 'hidden':''}]">
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
                    <Button icon="pi pi-pencil" class="p-button-rounded p-button-info" />
                    <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" />
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
