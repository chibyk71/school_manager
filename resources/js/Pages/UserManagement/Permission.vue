<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Checkbox, Column, DataTable } from 'primevue';

const permitable = [
    { label: 'Create', description: 'Allows creating new records' },
    { label: 'Update', description: 'Allows updating existing records' },
    { label: 'Edit Any', description: 'Allows editing any records' },
    { label: 'View', description: 'Allows viewing records' },
    { label: 'View Any', description: 'Allows viewing any records' },
    { label: 'Delete', description: 'Allows deleting records' },
    { label: 'Delete Any', description: 'Allows deleting any records' },
    { label: 'Restore', description: 'Allows restoring deleted records' },
]

</script>

<template>
    <AuthenticatedLayout title="Permission" :crumb="[{label: 'Dashboard'}, {label:'User Management'},{label:'Permissions'}]">
        <!-- Filter Section -->
        <DataTable>
            <Column selection-mode="multiple" />

            <Column header="Modules" field="modules"  />

            <Column v-for="{label, description} in permitable" :header="label">
                <Checkbox v-tooltip="{value: description}" />
            </Column>
            <template #header>
                <div class="card-header flex items-center justify-between flex-wrap pb-0">
                <h4 class="mb-3">Roles & Permissions List</h4>
            </div>
            </template>
        </DataTable>
        <!-- /Filter Section -->
    </AuthenticatedLayout>
</template>
