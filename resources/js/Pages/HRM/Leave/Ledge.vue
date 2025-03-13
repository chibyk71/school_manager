<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Leave Ledger" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Leave Ledger'}]" :button="[{label:'Add Allocation', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Staff" />
            <Column header="Leave Type" />
            <Column header="Encashed Days" />
            <Column header="Academic Session" />
            <!-- TODO: Will only show if user is allowed to view informations fromdifferent schools -->
            <Column header="School" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
