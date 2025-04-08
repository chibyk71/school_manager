<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable } from 'primevue';

const props = defineProps<{
    staffs: {
        id: number;
        enrollment_id: string;
        name: string;
        department: string;
        role: string;
        phone: string;
        email: string;
        enrollment_date: string;
        [x:string]: any
    }[];
}>()

console.log(props.staffs);

</script>

<template>
    <AuthenticatedLayout title="Staffs" :crumb="[{label:'Dashboard'},{label:'HRM'},{label:'Staffs'}]" :button="[{label:'Add Staff', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Enrollment Id." />
            <Column header="Name" />
            <Column header="Department" />
            <Column header="Role" />
            <Column header="Phone" />
            <Column header="Email" />
            <Column header="Enrollment Date" />
            <Column header="Action" />
        </DataTable>
    </AuthenticatedLayout>
</template>
