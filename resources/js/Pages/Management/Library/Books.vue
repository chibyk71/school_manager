<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Books" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Library' },{ label: 'Books' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Books', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" >
                      <template #filterfooter="slotProps"></template>
                    </Column>
                    <Column header="Book Name"></Column>
                    <Column header="Book No." />
                    <Column header="Publisher" />
                    <Column header="Author" />
                    <Column header="Subject" />
                    <Column header="Rack No." />
                    <Column header="Quantity" />
                    <Column header="Available" />
                    <Column header="Price"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
