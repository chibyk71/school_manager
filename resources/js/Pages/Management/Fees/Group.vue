<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Fee Concession" :crumb="[{ label: 'Dashboard' }, { label: 'Fees Collection' }, { label: 'Fees Concession' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Sections', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Description" />
                    <Column header="Type"></Column>
                    <Column header="Amount"></Column>
                    <Column header="Fee Type"></Column>
                    <Column header="Start Date"></Column>
                    <Column header="End Date"></Column>
                    <Column header="Status"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
