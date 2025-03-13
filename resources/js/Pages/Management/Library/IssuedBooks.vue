<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Issued Books" :crumb="[{ label: 'Dashboard' }, { label: 'Management' }, { label: 'Library' },{ label: 'Issued Books' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Books', icon: 'ti ti-plus-square' }]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="ID" >
                    </Column>
                    <Column header="Date Of Issue" />
                    <Column header="Due Date" />
                    <Column header="Issued To" />
                    <Column header="No. Of Books" />
                    <Column header="Books Returned" />
                    <Column header="Returned Date" />
                    <Column header="Remarks"></Column>
                    <Column header="Action" />
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>
</template>
