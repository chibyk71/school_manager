<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Routes" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Transport' }, {label: 'Routes'}]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Hostel', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" ></Column>
                    <Column header="Routes"></Column>
                    <Column header="No. Of Vehicles" />
                    <Column header="PickUp Point" />
                    <Column header="DropOff Point" />
                    <Column header="Description" />
                    <!-- Available or not -->
                    <Column header="Status" />
                    <Column header="Action" />
                    <template #loading></template>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
