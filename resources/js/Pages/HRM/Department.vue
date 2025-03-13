<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Departments" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Departments'}]" :button="[{label:'Add Department', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Name" sortable />
            <Column header="Status" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
