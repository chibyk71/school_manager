<!-- /js/Pages/Dashboard/Admin.vue -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Avatar, AvatarGroup, Badge, Button, Card, Carousel, Checkbox, DatePicker, Menu, Message, ProgressBar, Select, Tab, TabList, TabPanel, TabPanels, Tabs, Timeline } from 'primevue';
import Statistic from '../../Components/widgets/Statistic.vue';
import { quicklinksItems } from '@/store';
import QuickLink from './Partials/QuickLink.vue';
import Chart from 'primevue/chart';
const documentStyle = getComputedStyle(document.body);

const setChartOptions = () => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--p-text-color');

    return {
        plugins: {
            legend: {
                labels: {
                    cutout: '60%',
                    color: textColor
                }
            }
        }
    };
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout title="Admin Dashboard" :crumb="[{label:'Dashboard'}]" :buttons="[{label:'Add Student',icon:'pi pi-plus'}, {label: 'Fees Details', severity:'secondary', as:Link, href: '#'}]">

        <div class="mb-3 w-full">
            <Message closable severity="success"  class="rounded-full">
                <div class="flex items-center">
                    <Avatar class="mr-2" image="assets/img/profiles/avatar-27.jpg" shape='circle'></Avatar>
                    <p>Fahed III,C has paid Fees for the <strong class="mx-1">“Term1”</strong></p>
                </div>
            </Message>
            <!-- Dashboard Content -->
            <Card class="bg-slate-700 dark:bg-surface-600 mt-5 relative">
                <template #content>
                    <div class="overlay-img">
                        <img src="assets/img/bg/shape-04.png" alt="img" class="img-fluid absolute left-[40%] top-0">
                        <img src="assets/img/bg/shape-01.png" alt="img" class="img-fluid absolute bottom-0 left-[60%]">
                        <img src="assets/img/bg/shape-02.png" alt="img" class="img-fluid absolute right-20">
                        <img src="assets/img/bg/shape-03.png" alt="img" class="img-fluid absolute bottom-0 left-[15%]">
                    </div>
                    <div
                        class="flex xl:items-center xl:justify-between xl:flex-row flex-col">
                        <div class="mb-3 xl:mb-0">
                            <div class="flex items-center flex-wrap mb-2">
                                <h1 class="text-3xl/none font-bold text-surface-50 mr-2">Welcome Back, Mr. Herald</h1>
                                <Link href='#'><Avatar shape='circle' class='bg-gray-800 text-white' icon='ti ti-edit' /></Link>
                            </div>
                            <p class="text-white">Have a Good day at work</p>
                        </div>
                        <p class="text-white"><i class="ti ti-refresh mr-1"></i>Updated Recently on 15 Jun
                            2024</p>
                    </div>
                </template>
            </Card>
            <!-- /Dashboard Content -->
        </div>

        <!-- Statitics -->
        <div class="sm:columns-2 xxl:columns-4 gap-4 space-y-3">
            <!-- Total Students -->
             <Statistic title="Total Student" :stat="3654" image="assets/img/icons/student.svg" :percentage="1.2" :active="3643" :inactive="11" severity="bg-red-200/50" />
            <!-- /Total Students -->

            <!-- Total Teachers -->
             <Statistic title="Total Teachers" :stat="284" image="assets/img/icons/teacher.svg" :percentage="1.2" :active="254" :inactive="30" severity="bg-blue-200/50" />
            <!-- /Total Teachers -->

            <!-- Total Staff -->
            <Statistic title="Total Staff" :stat="162" image="assets/img/icons/staff.svg" :percentage="1.2" :active="161" :inactive="2" severity="bg-yellow-200/50" />
            <!-- /Total Staff -->

            <!-- Total Subjects -->
            <Statistic title="Total Subjects" :stat="82" image="assets/img/icons/subject.svg" :percentage="1.2" :active="80" :inactive="2" severity="bg-green-200/50" />
            <!-- /Total Subjects -->

        </div>

        <div class="grid xxl:grid-cols-3 xl:grid-cols-2 md:grid-cols-1 mt-4 gap-3">

            <!-- Schedules -->
            <Card class="card flex-1">
                <template #content>
                    <DatePicker inline fluid />
                    <h5 class="my-3 text-lg/none font-semibold">Upcoming Events</h5>
                    <div class="event-wrapper event-scroll">
                        <!-- Event Item -->
                        <div v-for="x in 3" class="border-l-[0.2rem] border-primary-700 shadow-sm p-3 mb-3">
                            <div class="flex items-center mb-3 pb-3 border-b">
                                <Avatar size="large" icon="ti ti-user-edit" class="text-teal-800 text-lg/none flex-0 bg-teal-200/30" />
                                <div class="flex-1 ml-2.5">
                                    <h6 class="mb-1 font-semibold text-xl/none">Parents, Teacher Meet</h6>
                                    <p class="flex items-center"><i class="ti ti-calendar mr-1"></i>15 July 2024</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="mb-0"><i class="ti ti-clock mr-1"></i>09:10AM - 10:50PM</p>
                                <AvatarGroup >
                                    <Avatar shape="circle" v-for="image in [1,7,2]" :image="`assets/img/parents/parent-0${image}.jpg`" />
                                </AvatarGroup>
                            </div>
                        </div>
                        <!-- /Event Item -->
                    </div>
                </template>
                <template #title>
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="card-title">Schedules</h4>
                        </div>
                        <!-- TODO: this button should open modal for creating new event -->
                         <Button variant="link" label="Add New" icon="ti ti-square-plus" />
                    </div>
                </template>
            </Card>
            <!-- /Schedules -->

            <!-- Attendance -->
            <div class="flex flex-col">

                <Card class="card">
                    <template #content>
                        <Tabs value="0">
                            <TabList>
                                <Tab value="0">Student</Tab>
                                <Tab value="1">Teachers</Tab>
                                <Tab value="2">Staff</Tab>
                            </TabList>
                            <TabPanels>
                                <TabPanel value="0">
                                    <div class="columns-3 gap-3">
                                        <div class="">
                                            <div class="p-card bg-surface-200/50 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>28</h5>
                                                    <p class="fs-12">Emergency</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="">
                                            <div class="p-card bg-surface-200/50 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>01</h5>
                                                    <p class="fs-12">Absent</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="">
                                            <div class="p-card bg-surface-200/50 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>01</h5>
                                                    <p class="fs-12">Late</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div id="student-chart" class="mb-4"></div>
                                    </div>
                                </TabPanel>

                                <TabPanel value="1" class="">
                                    <div class="columns-3 gap-x-3">
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>30</h5>
                                                    <p class="fs-12">Emergency</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>03</h5>
                                                    <p class="fs-12">Absent</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>03</h5>
                                                    <p class="fs-12">Late</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div id="teacher-chart" class="mb-4"></div>
                                        <a href="teacher-attendance.html" class="btn btn-light"><i
                                                class="ti ti-calendar-share mr-1"></i>View All</a>
                                    </div>
                                </TabPanel>
                                <TabPanel value="2">
                                    <div class="columns-3 gap-x-3">
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>45</h5>
                                                    <p class="fs-12">Emergency</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>01</h5>
                                                    <p class="fs-12">Absent</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="card bg-light-300 shadow-none border-0">
                                                <div class="card-body p-3 text-center">
                                                    <h5>10</h5>
                                                    <p class="fs-12">Late</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div id="staff-chart" class="mb-4"></div>
                                        <a href="staff-attendance.html" class="btn btn-light"><i
                                                class="ti ti-calendar-share mr-1"></i>View All</a>
                                    </div>
                                </TabPanel>
                            </TabPanels>
                        </Tabs>
                    </template>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Attendance</h4>
                            <div class="dropdown">
                                <Button icon="ti ti-calendar-due" label="Today" variant="text" @click="(e)=>$refs.attendance?.toggle(e)" />
                                <Menu ref="attendance" :model="[{label: 'This Week'},{label: 'Last Week'}]" popup />
                            </div>
                        </div>
                    </template>
                </Card>

                <div class="sm:columns-2 flex-1">

                    <!-- Best Performer -->
                    <div class="flex flex-col">
                        <div class="bg-green-800 p-3 rounded text-center flex-1 mb-4 pb-0  owl-height bg-01">
                            <div class="owl-carousel student-slider h-full">
                                <div class="item h-100">
                                    <div class="flex justify-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Best Performer</h5>
                                            <h4 class="mb-1 text-white">Rubell</h4>
                                            <p class="text-light">Physics Teacher</p>
                                        </div>
                                        <img src="assets/img/performer/performer-01.png" alt="img">
                                    </div>
                                </div>
                                <div class="item h-100">
                                    <div class="flex justify-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Best Performer</h5>
                                            <h4 class="mb-1 text-white">George Odell</h4>
                                            <p class="text-light">English Teacher</p>
                                        </div>
                                        <img src="assets/img/performer/performer-02.png" alt="img">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Best Performer -->

                    <!-- Star Students -->
                    <div class="col-sm-6 flex flex-col">
                        <div class="bg-info p-3 br-5 text-center flex-1 mb-4 pb-0 owl-height bg-02">
                            <div class="owl-carousel teacher-slider h-100">
                                <div class="item h-100">
                                    <div class="flex justify-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Star Students</h5>
                                            <h4 class="mb-1 text-white">Tenesa</h4>
                                            <p class="text-light">XII, A</p>
                                        </div>
                                        <img src="assets/img/performer/student-performer-01.png" alt="img">
                                    </div>
                                </div>
                                <div class="item h-100">
                                    <div class="flex justify-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Star Students</h5>
                                            <h4 class="mb-1 text-white">Michael </h4>
                                            <p>XII, B</p>
                                        </div>
                                        <img src="assets/img/performer/student-performer-02.png" alt="img">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Star Students -->

                </div>

            </div>
            <!-- /Attendance -->

            <div class="flex flex-col xl:col-span-2 gap-y-4">
                <!-- Quick Links -->
                <Card class="card flex-1">
                    <template #title>
                        <h4 class="card-title">Quick Links</h4>
                    </template>
                    <template #content>
                        <Carousel :num-visible="3" :show-navigators="false" :show-indicators="false" :value="quicklinksItems">
                            <template #item="slotProps">
                                <div class="item my-2">
                                    <QuickLink v-bind="slotProps.data[0]" />
                                    <QuickLink v-bind="slotProps.data[1]" />
                                </div>
                            </template>
                        </Carousel>
                    </template>
                </Card>
                <!-- /Quick Links -->

                <!-- Class Routine -->
                <Card class="card flex-1">
                    <template #content>
                        <div v-for="item in 3" class="flex items-center rounded border p-3 mb-3">
                            <Avatar :image="`assets/img/teachers/teacher-0${item}.jpg`" class="rounded-lg" />
                            <div class="w-full flex-1 ml-2">
                                <p class="mb-1 text-sm/none">Oct 2024</p>
                                <ProgressBar :value="80" class="h-1.5" :showValue="false"/>
                            </div>
                        </div>
                    </template>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Class Routine</h4>
                            <!-- TODO this button should open the modal fo creating new routine -->
                            <Button variant="link" size="small" class="font-medium" label="Add New" icon="ti ti-square-plus" />
                        </div>
                    </template>
                </Card>
                <!-- /Class Routine -->

                <!-- Class Wise Performance -->
                <Card class="flex-1">
                    <template #title>
                        <div class="flex items-center justify-between">
                        <h4 class="card-title">Performance</h4>
                        <Select modelValue='class I'size='small' :options="['class I','class II','class III','class IV']" />
                    </div>
                    </template>
                    <template #content>
                        <div class="md:flex items-center justify-between">
                            <div class="md:mr-3 mb-3 md:mb-0 w-full">
                                <div class="border border-dashed p-3 rounded flex items-center justify-between mb-1">
                                    <p class="mb-0 mr-2"><i class="ti ti-arrow-badge-down-filled mr-2 text-primary"></i>Top</p>
                                    <h5>45</h5>
                                </div>
                                <div class="border border-dashed p-3 rounded flex items-center justify-between mb-1">
                                    <p class="mb-0 mr-2"><i
                                            class="ti ti-arrow-badge-down-filled mr-2 text-warning"></i>Average
                                    </p>
                                    <h5>11</h5>
                                </div>
                                <div
                                    class="border border-dashed p-3 rounded flex items-center justify-between mb-0">
                                    <p class="mb-0 mr-2"><i class="ti ti-arrow-badge-down-filled mr-2 text-danger"></i>Below Avg
                                    </p>
                                    <h5>02</h5>
                                </div>
                            </div>
                            <Chart :data="[{
                                labels: ['top','average','below average'],
                                datasets: [
                                    {
                                        data: [540, 325, 702],
                                        backgroundColor: [documentStyle.getPropertyValue('--p-cyan-500'), documentStyle.getPropertyValue('--p-orange-500'), documentStyle.getPropertyValue('--p-gray-500')],
                                        hoverBackgroundColor: [documentStyle.getPropertyValue('--p-cyan-400'), documentStyle.getPropertyValue('--p-orange-400'), documentStyle.getPropertyValue('--p-gray-400')]
                                    }
                                ]
                            }]" type="doughnut" :options="setChartOptions()"  />
                        </div>
                    </template>
                </Card>
                <!-- /Class Wise Performance -->
            </div>

        </div>

        <div class="grid xl:grid-cols-2 xxl:grid-cols-3 gap-x-3 mt-4">

            <!-- Fees Collection -->
            <Card class="card flex-1">
                <template #content>
                    <div id="fees-chart"></div>
                </template>
                <template #title>
                    <div class="card-header  flex items-center justify-between">
                    <h4 class="card-title">Fees Collection</h4>

                        <Select modelValue="this year" :options="['This Month', 'this year', 'This Term']" />
                </div>
                </template>
            </Card>
            <!-- /Fees Collection -->

            <!-- Leave Requests -->
            <Card class="card flex-1">
                <template #title>
                    <div class="card-header  flex items-center justify-between">
                    <h4 class="card-title">Leave Requests</h4>
                    <Select :options="['this week','today']" size="small" />
                </div>
                </template>
                <template #content>
                    <Card class="card mb-2">
                        <template #content>
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center overflow-hidden mr-2">
                                    <Avatar image="assets/img/profiles/avatar-14.jpg" shape="circle" size="large" />
                                    <div class="overflow-hidden ml-2">
                                        <h6 class="mb-1 text-truncate">
                                            <a href="javascript:void(0);" class="mr-2">James</a>
                                            <Badge severity="danger">Emergency</Badge>
                                        </h6>
                                        <p class="text-truncate">Physics Teacher</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-x-2">
                                    <Button size="small" icon="pi pi-check" severity="success" />
                                    <Button size="small" icon="pi pi-times" severity="danger" />
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-top pt-3">
                                <p class="mb-0">Leave : <span class="fw-semibold">12 -13 May</span></p>
                                <p>Apply on : <span class="fw-semibold">12 May</span></p>
                            </div>
                        </template>
                    </Card>
                    <Card class="card mb-2">
                        <template #content>
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center overflow-hidden mr-2">
                                    <Avatar image="assets/img/profiles/avatar-14.jpg" shape="circle" size="large" />
                                    <div class="overflow-hidden ml-2">
                                        <h6 class="mb-1 text-truncate">
                                            <a href="javascript:void(0);" class="mr-2">James</a>
                                            <Badge severity="danger">Emergency</Badge>
                                        </h6>
                                        <p class="text-truncate">Physics Teacher</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-x-2">
                                    <Button size="small" icon="pi pi-check" severity="success" />
                                    <Button size="small" icon="pi pi-times" severity="danger" />
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-top pt-3">
                                <p class="mb-0">Leave : <span class="fw-semibold">12 -13 May</span></p>
                                <p>Apply on : <span class="fw-semibold">12 May</span></p>
                            </div>
                        </template>
                    </Card>
                </template>
            </Card>
            <!-- /Leave Requests -->

        </div>

        <div class="xl:columns-4 md:columns-2 columns-1 mt-4">

            <!-- Links -->
            <Link href="">
                <Card class="bg-primary-200/50">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-2">
                                <Avatar size="large" shape="circle" class="bg-yellow-500 text-surface-50" icon="ti ti-calendar-share" />
                                <div class="overflow-hidden">
                                    <h6 class="font-semibold text-default">View Attendance</h6>
                                </div>
                            </div>
                            <Button rounded icon="ti ti-chevron-right" variant="outlined" severity="contrast" size="small" class="" />
                        </div>
                    </template>
                </Card>
            </Link>
            <!-- /Links -->
            <!-- Links -->
            <Link href="">
                <Card class="bg-primary-200/50">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-2">
                                <Avatar size="large" shape="circle" class="bg-yellow-500 text-surface-50" icon="ti ti-speakerphone" />
                                <div class="overflow-hidden">
                                    <h6 class="font-semibold text-default">New Events</h6>
                                </div>
                            </div>
                            <Button rounded icon="ti ti-chevron-right" variant="outlined" severity="contrast" size="small" class="" />
                        </div>
                    </template>
                </Card>
            </Link>
            <!-- /Links -->

            <!-- Links -->
            <Link href="">
                <Card class="bg-primary-200/50">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-2">
                                <Avatar size="large" shape="circle" class="bg-yellow-500 text-surface-50" icon="ti ti-sphere" />
                                <div class="overflow-hidden">
                                    <h6 class="font-semibold text-default">Memebership Plans</h6>
                                </div>
                            </div>
                            <Button rounded icon="ti ti-chevron-right" variant="outlined" severity="contrast" size="small" class="" />
                        </div>
                    </template>
                </Card>
            </Link>
            <!-- /Links -->

            <!-- Links -->
            <Link href="">
                <Card class="bg-primary-200/50">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-2">
                                <Avatar size="large" shape="circle" class="bg-yellow-500 text-surface-50" icon="ti ti-moneybag" />
                                <div class="overflow-hidden">
                                    <h6 class="font-semibold text-default">Finance & Accounts</h6>
                                </div>
                            </div>
                            <Button rounded icon="ti ti-chevron-right" variant="outlined" severity="contrast" size="small" class="" />
                        </div>
                    </template>
                </Card>
            </Link>
            <!-- /Links -->

        </div>
        <div class="grid xxl:grid-cols-12 xl:grid-cols-2 mt-4 gap-4">
            <!-- Total Earnings -->
            <div class="xxl:col-span-4 flex flex-col gap-3">
                <Card class="card flex-1">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <h6 class="mb-1 text-md/sm font-normal">Total Earnings</h6>
                                <h2 class="text-xl/none font-semibold">$64,522,24</h2>
                            </div>
                            <Avatar icon="ti ti-user-dollar" class="bg-green-200/40" size="large" />
                        </div>
                        <div id="total-earning"></div>
                    </template>
                </Card>
                <Card class="card flex-1">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <h6 class="mb-1 text-md/sm font-normal">Total Expenses</h6>
                                <h2 class="text-xl/none font-semibold">$60,522,24</h2>
                            </div>
                            <Avatar icon="ti ti-user-dollar" class="bg-red-200/40" size="large" />
                        </div>
                        <div id="total-esxpenses"></div>
                    </template>
                </Card>
            <!-- /Total Earnings -->
            </div>

            <!-- Notice Board -->
            <div class="xxl:col-span-5 xl:col-span-2 order-3 xxl:order-2 flex">
                <Card class="card flex-1">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Notice Board</h4>
                            <!-- TODO replace href link -->
                            <Link href="notice-board.html" class="font-medium">View All</Link>
                        </div>
                    </template>
                    <template #content>
                        <Timeline :pt="{eventOpposite: {class: 'hidden'}}" class="w-full" :value="[
                            {icon:'ti ti-books', label:'New Syllabus Instructions', date:'11 Mar 2024'},
                            {icon:'ti ti-calendar', label:'New Syllabus Instructions', date:'11 Mar 2024'},
                            {icon:'ti ti-calendar', label:'New Syllabus Instructions', date:'11 Mar 2024'},
                            {icon:'ti ti-calendar', label:'New Syllabus Instructions', date:'11 Mar 2024'},
                            ]">
                            <template #content="{item, index}">
                                <div class="sm:flex items-center justify-between mb-4">
                                    <div class="flex items-center overflow-hidden mr-2 mb-2 sm:mb-0">
                                        <Avatar :icon="item.icon" size="large" shape="circle" class="" />
                                        <div class="overflow-hidden ml-2">
                                            <h6 class="text-truncate font-normal mb-1">{{ item.label }}</h6>
                                            <p class="text-xs/none font-normal"><i class="ti ti-calendar mr-2"></i>Added on : {{ item.date }}</p>
                                        </div>
                                    </div>
                                    <Badge severity="info">
                                        <i class="ti ti-clck mr-1"></i>
                                        20 Days
                                    </Badge>
                                </div>
                            </template>
                        </Timeline>
                    </template>
                </Card>
            </div>
            <!-- /Notice Board -->

            <!-- Fees Collection -->
            <div class="xxl:col-span-3 order-2 xxl:order-3 flex flex-col">
                <Card v-for="item in [
                    {label: 'Total Fees Collected', value: '$25,000,02',badge: '1.2%', severity:'success'},
                    {label: 'Fine Collected till date', value: '$4,56,64',badge: '1.2%', severity:'danger'},
                    {label: 'Student Not Paid', value: '$545',badge: '1.2%', severity:'info'},
                    {label: 'Total Outstanding', value: '$4,56,64',badge: '1.2%', severity:'danger'}
                ]" class="card flex-1 mb-2">
                    <template #content>
                        <p class="mb-2">{{ item.label }}</p>
                        <div class="flex items-end justify-between">
                            <h4>{{ item.value }}</h4>
                            <Badge :severity="item.severity"><i class="ti ti-chart-line mr-1"></i>{{ item.badge }}</Badge>
                        </div>
                    </template>
                </Card>
            </div>
            <!-- /Fees Collection -->
        </div>

        <div class="grid xxl:grid-cols-3 xl:grid-cols-2 gap-4">

            <!-- Top Subjects -->
            <div class="col-xxl-4 col-xl-6 flex">
                <Card class="card flex-1">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h4 class="card-title">Top Subjects</h4>
                            <Select size="small" modelValue='I' :options="['I', 'II', 'III', 'IV', 'V']" />
                        </div>
                    </template>
                    <template #content>
                        <Message icon="ti ti-info-square-rounded" severity="success">
                            <div class="text-sm/none">
                                These Result are obtained from the syllabus completion on the respective Class
                            </div>
                        </Message>
                        <ul class="flex flex-col rounded-lg border divide-y mt-3">
                            <li class="px-4 py-3" v-for="item in [1,2,3,4,5]">
                                <div class="flex items-center">
                                    <div class="sm:w-4/12 w-full">
                                        <p class="text-dark">Maths</p>
                                    </div>
                                    <div class="sm:w-8/12 w-full">
                                        <ProgressBar class="h-1.5" mode="determinate" :value="20" :showValue="false"></ProgressBar>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>
                </Card>

            </div>
            <!-- /Top Subjects -->

            <!-- Student Activity -->
            <div class="col-xxl-4 col-xl-6 flex">
                <Card class="card flex-1">
                    <template #content>
                        <div v-for="item in [9,10,11,12]" class="flex items-center overflow-hidden p-3 mb-3 border rounded">
                            <Avatar :image="`assets/img/students/student-${item}.jpg`" size="large" shape="circle" class="mr-2" />
                            <div class="overflow-hidden">
                                <h6 class="mb-1 text-truncate">1st place in "Chess”</h6>
                                <p>This event took place in Our School</p>
                            </div>
                        </div>
                    </template>
                    <template #title>
                        <div class="card-header  flex items-center justify-between">
                            <h4 class="card-title">Student Activity</h4>
                            <Select :options="['This Month', 'This Year', 'Last Week']" model-value="This Month" />
                        </div>
                    </template>
                </Card>

            </div>
            <!-- /Student Activity -->

            <!-- Todo -->
            <div class="xl:col-span-2 flex">
                <Card class="card flex-1">
                    <template #title>
                        <div class="card-header  flex items-center justify-between">
                        <h4 class="card-title">Todo</h4>
                        <Select :options="['This Month', 'This Year', 'Last Week']" model-value="This Month" />
                    </div>
                    </template>
                    <template #content>
                        <ul class="flex flex-col divide-y todo-list">
                            <li class=" py-3 px-0 pt-0">
                                <div class="sm:flex items-center justify-between">
                                    <div
                                        class="flex items-center overflow-hidden mr-2 todo-strike-content">
                                        <div class="form-check form-check-md mr-2">
                                            <Checkbox></Checkbox>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">Send Reminder to Students</h6>
                                            <p>01:00 PM</p>
                                        </div>
                                    </div>
                                    <Badge severity="success">Compeleted</Badge>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0">
                                <div class="sm-flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden mr-2">
                                        <div class="form-check form-check-md mr-2">
                                            <Checkbox></Checkbox>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 line-clamp-1 text-md/none fort-medium">Create Routine to new staff</h6>
                                            <p class='text-xs/none font-normal mt-0'>04:50 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-skyblue mt-2 mt-sm-0">Inprogress</span>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0">
                                <div class="sm-flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden mr-2">
                                        <div class="form-check form-check-md mr-2">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">Extra Class Info to Students</h6>
                                            <p>04:55 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-warning mt-2 mt-sm-0">Yet to Start</span>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0">
                                <div class="sm-flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden mr-2">
                                        <div class="form-check form-check-md mr-2">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">Fees for Upcoming Academics</h6>
                                            <p>04:55 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-warning mt-2 mt-sm-0">Yet to Start</span>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0 pb-0">
                                <div class="sm-flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden mr-2">
                                        <div class="form-check form-check-md mr-2">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">English - Essay on Visit</h6>
                                            <p>05:55 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-warning mt-2 mt-sm-0">Yet to Start</span>
                                </div>
                            </li>
                        </ul>
                    </template>
                </Card>
            </div>
            <!-- /Todo -->

        </div>
    </AuthenticatedLayout>
</template>
