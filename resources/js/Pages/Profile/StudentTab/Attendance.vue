<script setup lang="ts">
import { ListOfAcademicYears } from '@/store';
import { Avatar, Button, Card, Column, DataTable, Menu, MenuMethods, Select } from 'primevue';
import { useTemplateRef } from 'vue';

const sessions = ListOfAcademicYears()
const exportAttendance = useTemplateRef('export-attendance');
</script>

<template>
    <Card class="card">

        <div class="card-body pb-1">
            <div class="row">

                <!-- Total Present -->
                <div class="col-md-6 col-xxl-3 flex">
                    <div class="flex items-center rounded border p-3 mb-3 flex-1">
                        <span
                            class="avatar avatar-lg bg-primary-transparent rounded mr-2 flex-shrink-0 text-primary"><i
                                class="ti ti-user-check fs-24"></i></span>
                        <div class="ml-2">
                            <p class="mb-1">Present</p>
                            <h5>265</h5>
                        </div>
                    </div>
                </div>
                <!-- /Total Present -->

                <!-- Total Absent -->
                <div class="col-md-6 col-xxl-3 flex">
                    <div class="flex items-center rounded border p-3 mb-3 flex-1">
                        <span
                            class="avatar avatar-lg bg-danger-transparent rounded mr-2 flex-shrink-0 text-danger"><i
                                class="ti ti-user-check fs-24"></i></span>
                        <div class="ml-2">
                            <p class="mb-1">Absent</p>
                            <h5>05</h5>
                        </div>
                    </div>
                </div>
                <!-- /Total Absent -->

                <!-- Half Day -->
                <div class="col-md-6 col-xxl-3 flex">
                    <div class="flex items-center rounded border p-3 mb-3 flex-1">
                        <span
                            class="avatar avatar-lg bg-info-transparent rounded mr-2 flex-shrink-0 text-info"><i
                                class="ti ti-user-check fs-24"></i></span>
                        <div class="ml-2">
                            <p class="mb-1">Half Day</p>
                            <h5>01</h5>
                        </div>
                    </div>
                </div>
                <!-- /Half Day -->

                <!-- Late to School-->
                <div class="col-md-6 col-xxl-3 flex">
                    <div class="flex items-center rounded border p-3 mb-3 flex-1">
                        <span
                            class="avatar avatar-lg bg-warning-transparent rounded mr-2 flex-shrink-0 text-warning"><i
                                class="ti ti-user-check fs-24"></i></span>
                        <div class="ml-2">
                            <p class="mb-1">Late</p>
                            <h5>12</h5>
                        </div>
                    </div>
                </div>
                <!-- /Late to School-->

            </div>
        </div>
        <template #title>
            <div class="flex items-center justify-between flex-wrap pb-1">
                <h4 class="mb-3">Attendance</h4>
                <div class="flex items-center flex-wrap">
                    <div class="flex items-center flex-wrap mr-3">
                        <p class="text-dark mb-3 mr-2 text-xs/none">Last Updated on : 25 May 2024</p>
                        <Button icon="ti ti-refresh-dot" rounded size="small" />
                    </div>
                    <Select size="small" :options="sessions" :model-value="sessions[0]" />
                </div>
            </div>
        </template>
        <template #content></template>
    </Card>
    <Card class="card">

        <template #content>

            <div class="px-3">
                <div class="flex items-center flex-wrap">
                    <div v-for="data in [
                        {label:'Present',color:'bg-green-300',icon:'ti ti-check'},
                        {label:'Absent', icon:'ti ti-x',color:'bg-red-300'},
                        {label:'Late',icon:'ti ti-clock-x', color:'bg-sky-300'},
                        {label:'Halfday', icon:'ti ti-calendar-event', color:'bg-dark/30'},
                        {label: 'Holiday', icon:'ti ti-calendar-event', color:'bg-surface-300'}
                    ]" class="flex items-center bg-white border rounded-md p-2 mr-3 mb-3 gap-x-2">
                        <Avatar :icon="data.icon" :class="data.color" class="text-white" />
                        <p class="text-dark">{{ data.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Attendance List -->
                <DataTable>
                <Column sortable header="Date" />
                <Column header="Present" />
                <Column header="Absent" />
                <Column header="Late" />
                <Column header="Halfday" />
                <Column header="Holiday" />
                </DataTable>
            <!-- /Attendance List -->
        </template>
        <template #title>
            <div class="card-header flex items-center justify-between flex-wrap pb-1">
                <h4 class="mb-3">Leave & Attendance</h4>
                <div class="flex items-center flex-wrap gap-x-2">
                    <Select size="small" :options="['This Year','This Month','This Week']" model-value="This Year" />
                    <Button size="small" icon="ti ti-file-export" @click="(event) =>(exportAttendance as MenuMethods)?.toggle(event)" />
                    <Menu ref="export-attendance" :model="[{label:'Export as PDF',icon:'ti ti-file-type-pdf'},{label:'Export as Excel',icon:'ti ti-file-type-xls'}]" popup />
                </div>
            </div>
        </template>
    </Card>
</template>
