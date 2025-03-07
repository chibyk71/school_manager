<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Dashboard from '../Dashboard.vue';
import { Avatar, Badge, Button, Card, Tab, TabList, TabPanel, TabPanels, Tabs } from 'primevue';
import TimeTable from './StudentTab/TimeTable.vue';
import LeaveAndAttendance from './StudentTab/Leave.vue';
import Fees from './StudentTab/Fees.vue';
import Library from './StudentTab/Library.vue';
import Details from './StaffTab/Details.vue';
import Attendance from './StudentTab/Attendance.vue';
import Salary from './StaffTab/Salary.vue';


</script>

<template>
    <AuthenticatedLayout title="Staff Profile" :crumb="[{label:'Dashboard'},{label:'Profile'}]">

        <div class="grid grid-cols-12 gap-4">

            <!-- Student Information -->
            <div class="xxl:col-span-3 xl:col-span-4 col-span-12 space-y-4">
                <Card class="">
                    <!-- Basic Information -->
                    <template #content>
                        <h5 class="mb-3">Basic Information</h5>
                        <dl class="grid grid-cols-2 mb-0 gap-y-3">
                            <dt class="font-medium text-dark">Gender</dt>
                            <dd class="col-6">Female</dd>
                            <dt class="font-medium text-dark">Date Of Birth</dt>
                            <dd class="text-sm/none">25 Jan 2008</dd>
                            <dt class="font-medium text-dark">Blood Group</dt>
                            <dd class="text-sm/none">O +ve</dd>
                            <dt class="font-medium text-dark">Blood Group</dt>
                            <dd class="text-sm/none">Red</dd>
                            <dt class="font-medium text-dark">Reigion</dt>
                            <dd class="text-sm/none">Christianity</dd>
                            <dt class="font-medium text-dark">Caste</dt>
                            <dd class="text-sm/none">Catholic</dd>
                            <dt class="font-medium text-dark">Category</dt>
                            <dd class="text-sm/none">OBC</dd>
                            <dt class="font-medium text-dark">Mother tongue</dt>
                            <dd class="text-sm/none">English</dd>
                            <dt class="font-medium text-dark">Language</dt>
                            <dd class="text-sm/none">
                                <Badge value="English" severity="secondary" />
                                <Badge value="Spanish" severity="secondary" />
                            </dd>
                        </dl>
                    </template >
                    <!-- /Basic Information -->
                    <template #title>
                        <div class="flex items-center flex-wrap gap-x-3">
                            <Avatar shape="circle" image="assets/img/students/student-01.jpg" size="xlarge" class="border border-dashed mr-2" />
                            <div class="overflow-hidden">
                                <Badge severity="success" size="small" class=""><i class="ti ti-circle-filled text-sm/none mr-1"></i>Active</Badge>
                                <h5 class="line-clamp-1">Janet  Daniel</h5>
                                <p class="text-primary">AD1256589</p>
                            </div>
                        </div>
                    </template>

                </Card>

                <!-- Primary Contact Info -->
                <Card>
                    <template #content>
                        <h5 class="mb-3">Primary Contact Info</h5>

                        <div class="flex items-center mb-3 gap-x-2">
                            <Avatar icon="ti ti-phone">
                            </Avatar>
                            <div>
                                <span class="text-dark font-medium mb-1">Phone Number</span>
                                <p>+1 46548 84498</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-x-2">
                            <Avatar icon="ti ti-mail" />
                            <div>
                                <span class="text-dark font-medium mb-1">Email Address</span>
                                <p><a href="https://preskool.dreamstechnologies.com/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="dbb1bab59bbea3bab6abb7bef5b8b4b6">[email&#160;protected]</a></p>
                            </div>
                        </div>
                    </template>
                </Card>
                <!-- /Primary Contact Info -->
            </div>
            <!-- /Student Information -->

            <div class="xxl:col-span-9 xl:col-span-8 col-span-12">
                <Tabs value="details" scrollable>
                    <TabList>
                        <Tab value="details"><i class="ti ti-school mr-2"> </i>Staff Details</Tab>
                        <Tab value="time-table"><i class="ti ti-table-options mr-2"></i>Time Table</Tab>
                        <Tab value="leave">Leave</Tab>
                        <Tab value="attendance">Attendance</Tab>
                        <Tab value="payroll"><i class="ti ti-report-money mr-2"></i>Payroll </Tab>
                        <Tab value="library"><i class="ti ti-books mr-2"></i>Library</Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="details">
                            <Details />
                        </TabPanel>
                        <TabPanel value="time-table">
                            <TimeTable role="teacher" />
                        </TabPanel>
                        <TabPanel value="leave">
                            <LeaveAndAttendance />
                        </TabPanel>
                        <TabPanel value="attendance">
                            <Attendance />
                        </TabPanel>
                        <TabPanel value="payroll">
                            <Salary />
                        </TabPanel>
                        <TabPanel value="library">
                            <Library />
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
