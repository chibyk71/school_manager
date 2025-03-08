<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Subjects" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Subjects' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Subjects', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Code"></Column>
                    <Column header="Type"></Column>
                    <Column header="School Section"></Column>
                    <Column header="Status"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
