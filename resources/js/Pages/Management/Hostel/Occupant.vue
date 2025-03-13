<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Hostel Occupants" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Hostel' },{ label: 'Rooms' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Occupant', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" ></Column>
                    <Column header="Occupant"></Column>
                    <Column header="Bed No." />
                    <Column header="Dept" />
                    <Column header="Session" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
