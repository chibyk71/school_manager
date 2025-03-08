<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Exam Grades" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' },{ label: 'Exams'}, { label: 'Exam Grades' }]" :buttons="[{ label: 'Add Grades', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="Name" sortable></Column>
                    <Column header="Code" sortable></Column>
                    <Column header="Percentage" sortable></Column>
                    <Column header="Grade point" sortable />
                    <Column header="Remarks" />
                    <Column header="Section" />
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
