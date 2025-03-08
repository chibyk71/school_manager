<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Schools" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'School' }]"
        :buttons="[{ label: 'Add School', icon: 'ti ti-school' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" />
                    <Column header="Name"></Column>
                    <Column header="Short Name"></Column>
                    <Column header="Logo" />
                    <Column header="Action" />
                    <template #groupfooter="slotProps"></template>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
