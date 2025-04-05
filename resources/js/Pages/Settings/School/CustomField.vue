<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Column, DataTable, ToggleSwitch, useDialog } from 'primevue';
import { FilterModes, useSelectedResources } from '@/store';
import { modals, useDeleteResource } from '@/helpers';

const filter = {
    group: {value: null, modes: FilterModes.CONTAINS}
}

const { settings, resources } = defineProps({
    settings: [],
    resources: []
})

const dialog = useDialog(),
    { deleteResource } = useDeleteResource(),
    { selectedResourceIds, selectedResources } = useSelectedResources();

</script>

<template>
    <AuthenticatedLayout title="Custom Field" :crumb="[{ label: 'Setting' }, { label: 'School' }, { label: 'Custom Field' }]" :buttons="[{label: 'Delete Selected', severity: 'danger', class: selectedResources.length < 1? 'hidden': '', onClick: () => deleteResource('custom-field', selectedResourceIds)},{label: 'Add Field', icon: 'ti ti-plus', onClick: ()=> modals.open('custom-field',{ 'resources': resources})}]">
        <SettingsLayout>
            <template #left>
            </template>
            <template #main>
                <div class="mx-3">
                    <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                        <div class="mb-3">
                            <h5 class="mb-1">Custom Fields</h5>
                            <p>Custom Fields configuration</p>
                        </div>
                    </div>
                    <div class="block">
                        <DataTable v-model:selection="selectedResources" :filter="filter" :value="settings">
                            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
                            <Column field="resource" header="Resource"></Column>
                            <Column field="label" header="Label"></Column>
                            <Column field="field_type" header="Type"></Column>
                            <Column field="required" header="Required">
                              <template #body="slotProps">
                                <ToggleSwitch v-model="slotProps.data.required" />
                              </template>
                            </Column>
                            <Column field="order" header="Order">
                              <template #body="slotProps">{{ slotProps.data.order ?? 'default' }}</template>
                            </Column>
                            <Column field="default_value" header="Default"></Column>
                            <Column field="category" header="Group"></Column>
                            <Column field="status" header="Status"></Column>
                            <Column field="action" header="Action">
                              <template #body="slotProps">
                                <div class="flex items-center gap-x-2">
                                    <Button icon="ti ti-trash" @click="()=> deleteResource('custom-field', slotProps.data.id)" severity="danger" size="small" />
                                    <Button icon="ti ti-edit" @click="()=> modals.open('custom-field',{resource_data:slotProps.data})" severity="secondary" size="small"/>
                                </div>
                              </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
