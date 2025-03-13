<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Vehicless" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Transport' }, {label: 'Vehicles'}]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Vehicle', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" ></Column>
                    <Column header="Vehicle Model"></Column>
                    <Column header="Vehicle No." />
                    <Column header="Registration No." />
                    <Column header="GPS Id" />
                    <Column header="Route(s)" />
                    <Column header="Driver" />
                    <!-- Available or not -->
                    <Column header="Status" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
