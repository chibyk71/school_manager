<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Salary" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Payroll'},{label:'Salary'}]" :buttons="[{label:'Add Salary', icon:'ti ti-plus-square'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="Id" />
            <Column header="Base Salary" sortable />
            <Column header="Department" />
            <Column header="Position" />
            <Column header="Benefits" />
            <Column header="Tax" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
