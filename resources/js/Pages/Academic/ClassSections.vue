<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Class Sectionss" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Class Sections' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Sections', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Class Level" />
                    <Column header="Capacity"></Column>
                    <Column header="No. of Students"></Column>
                    <Column header="Status"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
