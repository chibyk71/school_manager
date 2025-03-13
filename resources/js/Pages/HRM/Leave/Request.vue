<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Leave Requests" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Leave Requests'}]" :button="[{label:'Add Request', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Staff" />
            <Column header="Leave Type" />
            <Column header="Requested Days" />
            <Column header="Start Date" />
            <Column header="Academic Session" />
            <Column header="Reason" />
            <Column header="Approved By" />
            <Column header="Approved At" />
            <Column header="Remarks" />
            <!-- TODO: Will only show if user is allowed to view informations fromdifferent schools -->
            <Column header="School" />
            <Column header="Status" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
