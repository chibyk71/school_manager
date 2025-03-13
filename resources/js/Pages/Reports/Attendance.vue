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
    <AuthenticatedLayout title="Attendance Report"
        :crumb="[{ label: 'Dashboard' }, { label: 'Report' }, { label: 'Attendance Report' }]">
        <!-- Filter Section -->
        <!-- <div class="filter-wrapper"> -->
        <!-- List Tab -->
        <!-- <div class="list-tab">
                <ul>
                    <li>
                        <a href="attendance-report.html" class="active">Attendance Report</a>
                    </li>
                    <li>
                        <a href="student-attendance-type.html">Students Attendance Type</a>
                    </li>
                    <li>
                        <a href="daily-attendance.html">Daily Attendance</a>
                    </li>
                    <li>
                        <a href="student-day-wise.html">Student Day Wise</a>
                    </li>
                    <li>
                        <a href="teacher-day-wise.html">Teacher Day Wise</a>
                    </li>
                    <li>
                        <a href="teacher-report.html">Teacher Report</a>
                    </li>
                    <li>
                        <a href="staff-day-wise.html">Staff Day Wise</a>
                    </li>
                    <li>
                        <a href="staff-report.html">Staff Report</a>
                    </li>
                </ul>
            </div> -->
        <!-- /List Tab -->
        <!-- </div> -->

        <div class="flex item-center justify-end">
            <ul class="flex items-center flex-wrap">
                <li v-for="item in AttendanceTypes"
                    class="border rounded-md bg-surface-0 py-2 px-2.5 text-[#202c4b] flex items-center mr-1 mb-1">
                    <span :class="item.iconbg"
                        class="text-surface-50 size-5 rounded-md flex items-center justify-center flex-0 mr-1"><i
                            :class="item.icon"></i></span>
                    {{ item.label }}
                </li>
            </ul>
        </div>

        <!-- Attendance List -->
        <Card class="divider-y">
            <template #title>
                <div class="flex items-center justify-between flex-wrap pb-0">
                    <h4 class="mb-3">Attendance Report</h4>
                    <div class="flex items-center flex-wrap">
                        <DatePicker selection-mode="range" />
                        <Select :options="['Students', 'Teachers', 'Staffs']" model-value="Students" />
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable>
                    <Column selection-mode="multiple" />
                    <Column header="S/N" />
                    <Column header="Name" />
                    <Column header="Percent %" />
                    <Column Header="Present" />
                    <Column header="Absent" />
                    <Column header="Late" />
                    <Column header=" On Leave" />
                    <Column header="Action"></Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Attendance List -->
    </AuthenticatedLayout>
</template>
