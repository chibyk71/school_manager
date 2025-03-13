<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Holidays" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Holidays'}]" :button="[{label:'Add Holidat=y', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Holiday Title" sortable />
            <Column header="Date" sortable />
            <Column header="Description" />
            <Column header="Status" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
