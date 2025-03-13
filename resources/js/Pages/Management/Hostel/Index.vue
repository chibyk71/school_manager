<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Hostels" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Hostel List' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Hostel', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" ></Column>
                    <Column header="Hostel Name"></Column>
                    <Column header="Type" />
                    <Column header="Address" />
                    <Column header="Room No." />
                    <Column header="Subject" />
                    <Column header="Description" />
                    <Column header="Available" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
