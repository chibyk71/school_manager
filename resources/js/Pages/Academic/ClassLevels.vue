<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Class Levels" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'School Sections' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Sections', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Display Name" />
                    <Column header="School Section"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
