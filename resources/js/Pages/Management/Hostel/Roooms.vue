<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Hostel Rooms" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Hostel' },{ label: 'Rooms' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Hostel Room', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" ></Column>
                    <Column header="Room No."></Column>
                    <Column header="Room Type" />
                    <Column header="No.of Beds" />
                    <Column header="Cost Per Bed" />
                    <Column header="Available Beds" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
