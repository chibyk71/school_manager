<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Column, DataTable, IconField, InputIcon, InputText, ToggleSwitch, useDialog } from 'primevue';
import { FilterModes, useSelectedResources } from '@/store';
import { modals, useDeleteResource } from '@/helpers';
import TableData from '@/Components/misc/TableData.vue';
import { ColumnDefinition, CustomField } from '@/types';

const filter = {
    group: {value: null, modes: FilterModes.CONTAINS}
}

const { settings, resources } = defineProps<{
    settings: Array<CustomField>,
    resources: Array<string>,
    columns: ColumnDefinition[],
}>()
</script>

<template>
    <AuthenticatedLayout title="Custom Field" :crumb="[{ label: 'Setting' }, { label: 'School' }, { label: 'Custom Field' }]" :buttons="[{label: 'Add Field', icon: 'ti ti-plus', onClick: ()=> modals.open('custom-field',{ 'resources': resources})}]">
        <SettingsLayout>
            <template #main>
                <div class="mx-3">
                    <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                        <div class="mb-3">
                            <h5 class="mb-1">Custom Fields</h5>
                            <p>Custom Fields configuration</p>
                        </div>
                    </div>
                    <div class="block">
                        <TableData :global-filter-fields="['label, resource']" :rows="settings" :endpoint="''" :columns="columns" class="mt-4">
                        </TableData>
                    </div>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
