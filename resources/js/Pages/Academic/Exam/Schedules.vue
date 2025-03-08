<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Exam Schedules" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' },{ label: 'Exams'}, { label: 'Exam Schedules' }]" :buttons="[{ label: 'Add Schedule', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Subject"></Column>
                    <Column header="Class Level"></Column>
                    <Column header="Date"></Column>
                    <Column header="Start Time" />
                    <Column header="Duration" />
                    <Column header="Venue" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
