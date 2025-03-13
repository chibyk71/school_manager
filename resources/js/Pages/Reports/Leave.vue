<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable, DatePicker, Select, Tabs } from 'primevue';

</script>

<template>
    <AuthenticatedLayout title="Leave Report" :crumb="[{ label: 'Dashboard' }, { label: 'Report' }, { label: 'Leave Report' }]">
        <!-- Attendance List -->
        <Card class="divider-y">
            <template #title>
                <div class="flex items-center justify-between flex-wrap pb-0">
                    <h4 class="mb-3">Leave Report List</h4>
                    <div class="flex items-center flex-wrap">
                        <DatePicker selection-mode="range" />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="S/N" >
                    </Column>
                    <Column header="Staff Name" />
                    <Column header="Class Level" />
                    <Column Header="Class Section" />
                    <Column header="NO. Student" />
                    <Column header="Action"></Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Attendance List -->
    </AuthenticatedLayout>
</template>
