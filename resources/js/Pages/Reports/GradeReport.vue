<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ListOfAcademicYears } from '@/store';
import { Card, Column, DataTable, DatePicker, Select, Tabs } from 'primevue';
import { ref } from 'vue';
    const academicYears = ListOfAcademicYears();
</script>

<template>
    <AuthenticatedLayout title="Grade Report" :crumb="[{ label: 'Dashboard' }, { label: 'Report' }, { label: 'Grade Report' }]">
        <!-- Attendance List -->
        <Card class="divider-y">
            <template #title>
                <div class="flex items-center justify-between flex-wrap pb-0">
                    <h4 class="mb-3">Grade Report List</h4>
                    <div class="flex items-center flex-wrap">
                        <!-- List of class section s to use as the active section to show reports Of -->
                        <Select :options="[]" />
                        <Select :options="academicYears" :model-value="academicYears[0]" />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="Addmission Number" />
                    <Column header="Student Name" >
                    </Column>
                    <Column header="Class Level" />
                    <Column Header="Percent %" />
                    <Column header="Grade" />
                    <Column header="English" />
                    <Column header="Mathematics" />
                    <!-- And so on, list other subjrcts -->
                     <Column header="Total" />
                    <Column header="Action"></Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Attendance List -->
    </AuthenticatedLayout>
</template>
