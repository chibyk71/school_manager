<script setup lang="ts">
import CircleProgress from '@/Components/misc/CircleProgress.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { filterByTimeOptions, StudentQuickLinks } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, Badge, Button, Card, DatePicker, Message, ProgressBar, Select } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Student Dashboard" :crumb="[{label:'dashboard'}]">
        <div class="grid xxl:grid-cols-12 gap-4">
            <div class="xxl:col-span-8 flex">
                <div class="grid xl:grid-cols-2 flex-1 gap-4">
                    <!-- Profile -->
                    <div class="col-xl-6 flex flex-1 gap-y-4 flex-col">
                        <Card class="bg-dark relative">
                            <template #content>
                                <div class="flex items-center gap-y-3 mb-3">
                                    <Avatar :pt="{image:{class:'rounded-2xl'}}" class="size-20 rounded-2xl" image="assets/img/students/student-13.jpg" />
                                    <div class="block ml-3 space-y-1.5">
                                        <Badge value="#ST1234546" severity="secondary" />
                                        <h3 class="line-clamp-1 text-lg/none front-semibold text-white mb-1">Angelo Riana</h3>
                                        <div class="flex items-center flex-wrap gap-y-2 text-gray-200">
                                            <span class="border-r mr-2 pr-2">Class : III, C</span>
                                            <span>Roll No : 36545</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between profile-footer flex-wrap gap-y-3 pt-4">
                                    <div class="flex items-center gap-x-2">
                                        <h6 class="text-white">1st Quarterly</h6>
                                        <Badge severity="success">
                                            <i  class="ti ti-circle-filled" /> <span>Pass</span>
                                        </Badge>
                                    </div>
                                    <Button size="small" label="Edit Profile" :as="Link" href="#" />
                                </div>
                                <div class="student-card-bg">
                                    <img class="absolute left-[80%] top-0" src="assets/img/bg/circle-shape.png" alt="Bg">
                                    <img class="absolute left-[60%] bottom-0" src="assets/img/bg/shape-02.png" alt="Bg">
                                    <img class="absolute left-3 top-2" src="assets/img/bg/shape-04.png" alt="Bg">
                                    <img class="absolute left-[40%] bottom-0" src="assets/img/bg/blue-polygon.png" alt="Bg">
                                </div>
                            </template>
                        </Card>
                        <Card class="card flex-1">
                            <template #title>
                                <div class="flex items-center justify-between border-b pb-2">
                                    <h4 class="card-title">Today’s Class</h4>
                                    <DatePicker size="small" dateFormat="dd/mm/yy" />
                                </div>
                            </template>
                            <template #content>
                                <div v-for="i in 3" class="p-card mb-2">
                                    <div class="flex items-center justify-between flex-wrap p-3 pb-1">
                                        <div class="flex items-center flex-wrap mb-2">
                                            <Avatar image="assets/img/parents/parent-07.jpg" size="large" />
                                            <div class="ml-3">
                                                <h6 class="mb-1 text-lg/none font-semibold line-through">English</h6>
                                                <span class="text-xs/none"><i class="ti ti-clock mr-2"></i>09:00 - 09:45 AM</span>
                                            </div>
                                        </div>
                                        <Badge severity="success" >
                                            <i class="ti ti-circle-filled mr-1"></i>Completed
                                        </Badge>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <!-- /Profile -->

                    <!-- Attendance -->
                    <Card class="flex-1">
                        <template #title>
                            <div class="card-header flex items-center justify-between">
                            <h4 class="card-title">Attendance</h4>
                            <Select :options="filterByTimeOptions">
                                <template #option="slotProps">
                                    <option value="{{slotProps.option.value}}">{{ slotProps.option.label}}</option>
                                </template>
                                <template #value="slotProps">
                                    {{ slotProps.value?.label ?? "" }}
                                </template>
                            </Select>
                        </div>
                        </template>
                        <template #content>
                            <div class="attendance-chart">
                                <p class="mb-3"><i class="ti ti-calendar-heart text-primary mr-2"></i>No of total working days <span class="fw-medium text-dark"> 28 Days</span></p>
                                <div class="border rounded p-3">
                                    <div class="flex gap-x-3 divide-x">
                                        <div class="col text-center flex-1">
                                            <p class="mb-1">Present</p>
                                            <h5>25</h5>
                                        </div>
                                        <div class="col text-center flex-1">
                                            <p class="mb-1">Absent</p>
                                            <h5>2</h5>
                                        </div>
                                        <div class="col text-center flex-1">
                                            <p class="mb-1">Halfday</p>
                                            <h5>0</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mb-4">
                                    <!-- TODO: Attendance Chart -->
                                    <div id="attendance_chart"></div>
                                </div>
                                <div class="bg-surface-200/30 dark:bg-surface-800 rounded border p-3 mb-0">
                                    <div class="flex items-center justify-between flex-wrap mb-1">
                                        <h6 class="mb-2">Last 7 Days </h6>
                                        <p class="text-xs mb-2">14 May 2024 - 21 May 2024</p>
                                    </div>
                                    <div class="flex items-center rounded gap-1 justify-evenly flex-wrap">
                                        <Badge v-for="i in ['M','T','W','TH','F','S','S']" severity="success" class="badge-lg">{{ i }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                    <!-- /Attendance -->

                    <!-- Fees -->
                    <div class="xl:col-span-2">
                        <div class="grid xl:grid-cols-4 sm:grid-cols-2 gap-4">
                            <div class="" v-for="item in StudentQuickLinks">
                            <Card :class="`border-b-4 ${item.border_color} animate-card w-full`">
                                <template #content>
                                    <Link :href="item.url">
                                        <div class="card-body">
                                            <div class="flex items-center">
                                                <Avatar :icon="item.icon" shape="circle" />
                                                <h6 class="ml-2">{{ item.label }}</h6>
                                            </div>
                                        </div>
                                    </Link>
                                </template>
                            </Card>
                            </div>
                        </div>
                    </div>
                    <!-- /Fees -->

                </div>
            </div>

            <!-- Schedules -->
            <div class="xxl:col-span-4">
                <Card class="card">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Schedules</h4>
                            <Button icon="ti ti-square-plus" label="Add New" size="small" />
                        </div>
                    </template>
                    <template #content>
                        <DatePicker inline fluid />
                        <h5 class="my-3 text-lg/none font-medium">Exams</h5>
                        <div class="p-3 pb-0 mb-3 border rounded">
                            <div class="flex items-center justify-between">
                                <h5 class="mb-2">1st Quarterly</h5>
                                <Badge>
                                    <span><i class="ti ti-clock mr-1"></i>19 Days More</span>
                                </Badge>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="mb-3">
                                    <h6 class="mb-1">Mathematics</h6>
                                    <p><i class="ti ti-clock mr-1"></i>01:30 - 02:15 PM</p>
                                </div>
                                <div class="mb-3 text-end">
                                    <p class="mb-1"><i class="ti ti-calendar-bolt mr-1"></i>06 May 2024</p>
                                    <p class="text-primary">Room No : 15</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 pb-0 mb-3 border rounded">
                            <div class="flex items-center justify-between">
                                <h5 class="mb-3">2nd Quarterly</h5>
                                <Badge>
                                    <span><i class="ti ti-clock mr-1"></i>19 Days More</span>
                                </Badge>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="mb-3">
                                    <h6 class="mb-1">Physics</h6>
                                    <p><i class="ti ti-clock mr-1"></i>01:30 - 02:15 PM</p>
                                </div>
                                <div class="mb-3 text-end">
                                    <p class="mb-1"><i class="ti ti-calendar-bolt mr-1"></i>07 May 2024</p>
                                    <p class="text-primary">Room No : 15</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <!-- /Schedules -->

        </div>

        <div class="grid xxl:grid-cols-12">

            <!-- Performance -->
            <div class="xxl:col-span-7 flex">
                <Card class="card flex-1">
                    <div class="card-body pb-0">
                        <div id="performance_chart"></div>
                    </div>
                    <template #title>
                        <div class="card-header flex items-center justify-between">
                            <h4 class="card-title">Performance</h4>
                            <Select :options="filterByTimeOptions">
                                <template #option="slotProps">
                                    <option value="{{slotProps.option.value}}">{{ slotProps.option.label }}</option>
                                </template>
                                <template #value="slotProps">
                                    {{ slotProps.value?.label ?? "" }}
                                </template>
                            </Select>
                        </div>
                    </template>
                    <template #content></template>
                </Card>
            </div>
            <!-- /Performance -->

            <!-- Home Works -->
            <div class="xxl:col-span-5 flex mt-4">
                <Card class="card flex-1">
                    <template #content>
                        <ul class="list-group list-group-flush">
                            <li v-for="i in 5" class="list-group-item py-3 px-0 pb-0">
                                <div class="flex items-center justify-between flex-wrap">
                                    <div class="flex items-center overflow-hidden mb-3">
                                        <Avatar class="size-20 rounded-xl" :pt="{image:{class:'rounded-xl'}}" image="assets/img/home-work/home-work-01.jpg" size="large" />
                                        <div class="overflow-hidden ml-2">
                                            <p class="flex items-center text-blue-300"><i class="ti ti-tag mr-2"></i>Physics</p>
                                            <h6 class="text-truncate"><a href="class-home-work.html">Write about Theory of Pendulum</a></h6>
                                            <div class="flex items-center flex-wrap">
                                                <div class="flex items-center border-r mr-1 pr-1">
                                                    <Avatar class="size-4" image="assets/img/teachers/teacher-01.jpg" shape="circle" />
                                                    <p class="dark:texty-color ml-2.5">Aaron</p>
                                                </div>
                                                <p class="text-color">Due by : 16 Jun 2024</p>
                                            </div>
                                        </div>
                                    </div>
                                    <CircleProgress severity="blue-500" value="65" />
                                </div>
                            </li>
                        </ul>
                    </template>
                    <template #title>
                        <div class="card-header flex items-center justify-between">
                        <h4 class="card-titile">Home Works</h4>
                        <Select size="small" model-value="All Subject" :options="['All Subject', 'Physics', 'Chemistry', 'Maths']" />
                    </div>
                    </template>
                </Card>
            </div>
            <!-- /Home Works -->

        </div>

        <div class="grid :xxl:grid-cols-12 xl:grid-cols-2 gap-4 mt-4">

            <!-- Leave Status -->
            <Card class="card">
                <template #content>
                    <div v-for="i in 3" class="bg-surface-100 dark:bg-dark sm:flex items-center justify-between p-3 mb-3">
                        <div class="flex items-center mb-2 mb-sm-0">
                            <Avatar size="large" icon="ti ti-brand-socket-io" />
                            <div class="ml-2">
                                <h6 class="mb-1">Emergency Leave</h6>
                                <p class="text-color text-sm/none">Date : 15 Jun 2024</p>
                            </div>
                        </div>
                        <Badge>
                            <i class="ti ti-circle-filled fs-5 mr-1"></i>Pending
                        </Badge>
                    </div>
                </template>
                <template #title>
                <div class="card-header flex items-center justify-between">
                    <h4 class="card-title">Leave Status</h4>
                    <Select size="small" :model-value="filterByTimeOptions[0]" :options="filterByTimeOptions">
                      <template #value="slotProps">{{ slotProps.value?.label ?? "" }}</template>
                    </Select>
                </div>
                </template>
            </Card>
            <!-- /Leave Status -->

            <!-- Exam Result -->
            <Card class="card">
                <template #content>
                    <div class="flex items-center flex-wrap gap-x-3">
                        <Badge v-for="{subject, score} in [{subject:'Maths', score:100}, {subject:'Physics', score:92}, {subject:'Chemistry', score:90}, {subject:'English', score:80}]" :value="`${subject} : ${score}`"/>
                    </div>
                    <div id="exam-result-chart"></div>
                </template>
                <template #title>
                    <div class="flex items-center justify-between">
                    <h4 class="card-title">Exam Result</h4>
                    <Select size="small" :options="filterByTimeOptions" :model-value="filterByTimeOptions[0]">
                        <template #value="slotProps">
                           {{ slotProps.value.label ?? "" }}
                        </template>
                    </Select>
                </div>
                </template>
            </Card>
            <!-- /Exam Result -->

            <!-- Fees Reminder -->
            <div class="xl:col-span-2">
                <Card class="card flex-1" :pt="{content: {class:'space-y-2'}}">
                    <template #title>
                        <div class="card-header flex items-center justify-between">
                            <h4 class="card-titile">Fees Reminder</h4>
                            <Button variant="link" size="small" :as='Link' href="fees-assign.html" class="link-primary fw-medium">View All</Button>
                        </div>
                    </template>
                    <template #content>
                        <div v-for="i in 5" class="bg-suface-100 dark:bg-dark p-4 rounded-lg flex items-center justify-between py-3">
                            <div class="flex items-center overflow-hidden mr-2">
                                <Avatar icon="ti ti-bus-stop" size="large" shape="circle" />
                                <div class="overflow-hidden ml-2.5">
                                    <h6 class="text-truncate mb-1">Transport  Fees</h6>
                                    <p class="text-xs/none text-muted-color">$2500</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <h6 class="mb-1">Last Date</h6>
                                <p class="text-color-emphasis text-sm/tight">25 May 2024</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <!-- Fees Reminder -->

        </div>

        <div class="grid xxl:grid-cols-3 xl:grid-cols-2 mt-4 gap-4">

            <!-- Notice Board -->
                <Card class="card flex-1">
                    <template #content>
                        <div class="notice-widget">
                            <div v-for="i in 6" class="flex items-center justify-between mb-4">
                                <div class="flex items-center overflow-hidden mr-2">
                                    <Avatar icon="ti ti-books" class="bg-primary-200/40" shape="circle" />
                                    <div class="overflow-hidden ml-2">
                                        <h6 class="text-truncate text-md/none font-medium">New Syllabus Instructions</h6>
                                        <p class="text-xs text-color"><i class="ti ti-calendar mr-2"></i>Added on : 11 Mar 2024</p>
                                    </div>
                                </div>
                                <Button :as="Link" href="notice-board" variant="link" size="small"><i class="ti ti-chevron-right text-base/none"></i></Button>
                            </div>
                        </div>
                    </template>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Notice Board</h4>
                            <Button :as="Link" href="/notice-board" size="small" variant="link">View All</Button>
                        </div>
                    </template>
                </Card>
            <!-- /Notice Board -->

            <!-- Syllabus -->
                <Card class="">
                    <template #title>
                        <h4>Syllabus</h4>
                    </template>
                    <template #content>
                        <Message severity="info">
                            <div class="text-sm/none">
                                These Result are obtained from the syllabus completion on the respective Class
                            </div>
                            <template #icon="slotProps">
                                <i class="ti ti-info-square-rounded mr-2 size-3.5"></i>
                            </template>
                        </Message>
                        <ul class="list-group border rounded-lg divide-y mt-3">
                            <li class="list-group-item p-2" v-for="i in 7">
                                <div class="flex flex-wrap items-center w-full">
                                    <div class="sm:w-4/12 w-full">
                                        <p class="text-dark dark:text-surface-100">Maths</p>
                                    </div>
                                    <div class="sm:w-8/12 w-full">
                                        <ProgressBar :show-value="false" :value="30" class="h-2" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>
                </Card>
            <!-- /Syllabus -->
        </div>
    </AuthenticatedLayout>
</template>
