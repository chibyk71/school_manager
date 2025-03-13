<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Fee Types" :crumb="[{ label: 'Dashboard' }, { label: 'Fees Collection' }, { label: 'Fees Type' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Fee Type', icon: 'ti ti-plus-circle' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Description" />
                    <Column header="Fee Group" />
                    <Column header="Status"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
