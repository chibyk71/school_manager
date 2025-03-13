<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Salary Structure" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Payroll'},{label:'Salary'},{label:'Details'}]" :buttons="[{label:'Add Salary Details', icon:'ti ti-plus-square'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="Id" />
            <Column header="Salary" sortable />
            <Column header="Title" />
            <Column header="Type" />
            <Column header="Benefits" />
            <Column header="Tax" />
            <Column header="Action" />
            <template #header>
                
            </template>
        </DataTable>
    </AuthenticatedLayout>
</template>
