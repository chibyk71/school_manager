<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Home Work" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' },{ label: 'Exams'}, { label: 'Exam Grades' }]" :buttons="[{ label: 'Add Grades', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="Class Section" sortable></Column>
                    <Column header="Subject" sortable></Column>
                    <Column header="Date Created" sortable></Column>
                    <Column header="Created By"/>
                    <Column header="Submission Date" sortable></Column>
                    <Column header="Submitted By"/>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
