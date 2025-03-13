<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable, DatePicker, Select, Tabs } from 'primevue';
import { ref } from 'vue';

const AttendanceTypes = ref([
    { label: 'Present', icon: 'ti ti-checks', iconbg: 'bg-green-300' },
    { label: 'Absent', icon: 'ti ti-x', iconbg: 'bg-red-300' },
    { label: 'Late', icon: 'it ti-clock-x', iconbg: 'bg-yellow-300' },
    { label: "Holiday", icon: 'ti ti-clock-up', iconbg: 'bg-blue-300' }
])

</script>

<template>
    <AuthenticatedLayout title="Class Report" :crumb="[{ label: 'Dashboard' }, { label: 'Report' }, { label: 'Class Report' }]">
        <!-- Attendance List -->
        <Card class="divider-y">
            <template #title>
                <div class="flex items-center justify-between flex-wrap pb-0">
                    <h4 class="mb-3">Class Report List</h4>
                    <div class="flex items-center flex-wrap">
                        <DatePicker selection-mode="range" />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="S/N" />
                    <Column header="School Section" />
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
