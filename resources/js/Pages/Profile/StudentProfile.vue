<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Dashboard from '../Dashboard.vue';
import { Avatar, Badge, Button, Card, Tab, TabList, TabPanel, TabPanels, Tabs } from 'primevue';
import Details from './StudentTab/Details.vue';


</script>

<template>
    <AuthenticatedLayout title="Student Profile" :crumb="[{label:'Dashboard'},{label:'Profile'}]">

        <div class="grid grid-cols-12 gap-4">

            <!-- Student Information -->
            <div class="xxl:col-span-3 xl:col-span-4 col-span-12 space-y-4 theiaStickySidebar">
                <Card class="">
                    <!-- Basic Information -->
                    <template #content>
                        <h5 class="mb-3">Basic Information</h5>
                        <dl class="grid grid-cols-2 mb-0">
                            <dt class="col-6 font-medium text-dark mb-3">Roll No</dt>
                            <dd class="col-6 mb-3">35013</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Gender</dt>
                            <dd class="col-6 mb-3">Female</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Date Of Birth</dt>
                            <dd class="col-6 mb-3">25 Jan 2008</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Blood Group</dt>
                            <dd class="col-6 mb-3">O +ve</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Blood Group</dt>
                            <dd class="col-6 mb-3">Red</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Reigion</dt>
                            <dd class="col-6 mb-3">Christianity</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Caste</dt>
                            <dd class="col-6 mb-3">Catholic</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Category</dt>
                            <dd class="col-6 mb-3">OBC</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Mother tongue</dt>
                            <dd class="col-6 mb-3">English</dd>
                            <dt class="col-6 font-medium text-dark mb-3">Language</dt>
                            <dd class="col-6 mb-3"><span class="badge badge-light text-dark mr-2">English</span><span class="badge badge-light text-dark">Spanish</span></dd>
                        </dl>
                        <Button size="small" fluid label="Add Fees" />
                    </template >
                    <!-- /Basic Information -->
                    <template #title>
                        <div class="flex items-center flex-wrap row-gap-3">
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

                <!-- Sibiling Information -->
                <Card class="card border-surface-50">
                    <template #content class="card-body">
                        <h5 class="mb-3">Sibiling Information</h5>
                        <div class="flex items-center bg-surface-100/30 rounded-sm p-3 mb-3">
                            <Avatar image="assets/img/students/student-06.jpg" size="large" />
                            <div class="ml-2">
                                <h5 class=" text-sm">Ralph Claudia</h5>
                                <p class="text-xs">III, B</p>
                            </div>
                        </div>
                        <div class="flex items-center bg-surface-100/30 rounded-sm p-3 mb-3">
                            <Avatar image="assets/img/students/student-07.jpg" size="large" />
                            <div class="ml-2">
                                <h5 class=" text-sm">Ralph Claudia</h5>
                                <p class="text-xs">III, B</p>
                            </div>
                        </div>
                    </template>
                </Card>
                <!-- /Sibiling Information -->

                <!-- Transport Information -->
                <Card class="border-surface-50">
                    <template #content class="card-body pb-1">
                        <Tabs value="hostel">
                            <TabList>
                                <Tab value="hostel">Hostel</Tab>
                                <Tab value="transport">Transportation</Tab>
                            </TabList>
                            <TabPanels>
                                <TabPanel value="hostel">
                                    <div class="flex items-center mb-3">
                                        <span class="avatar avatar-md bg-light-300 rounded mr-2 flex-shrink-0 text-default"><i class="ti ti-building-fortress fs-16"></i></span>
                                        <div>
                                            <h6 class="fs-14 mb-1">HI-Hostel, Floor</h6>
                                            <p class="text-primary">Room No : 25</p>
                                        </div>
                                    </div>
                                </TabPanel>
                                <TabPanel value="transport">
                                    <div class="flex items-center gap-x-2 mb-3">
                                        <Avatar icon="ti ti-bus" />
                                        <div>
                                            <span class="text-xs/none">Route</span>
                                            <p class="text-dark">Newyork</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2">
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <span class="text-xs/none">Bus Number</span>
                                                <p class="text-dark">AM 54548</p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <span class="text-xs/none">Pickup Point</span>
                                                <p class="text-dark">Cincinatti</p>
                                            </div>
                                        </div>
                                    </div>
                                </TabPanel>
                            </TabPanels>
                        </Tabs>
                    </template>
                </Card>
                <!-- /Transport Information -->

            </div>
            <!-- /Student Information -->

            <div class="xxl:col-span-9 xl:col-span-8 col-span-12">
                <Tabs value="details" scrollable>
                    <TabList>
                        <Tab value="details"><i class="ti ti-school mr-2"> </i>Student Details</Tab>
                        <Tab value="time-table"><i class="ti ti-table-options mr-2"></i>Time Table</Tab>
                        <Tab value="leave">Leave & Attendance</Tab>
                        <Tab value="fees"><i class="ti ti-report-money mr-2"></i>Fees </Tab>
                        <Tab value=exam><i class="ti ti-bookmark-edit mr-2"></i>Exam & Results</Tab>
                        <Tab value="library"><i class="ti ti-books mr-2"></i>Library</Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="details">
                            <Details />
                        </TabPanel>
                        <TabPanel value="time-table">
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
