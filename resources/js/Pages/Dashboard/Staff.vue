<script setup lang="ts">
import Charts from '@/Components/misc/Charts.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { filterByTimeOptions } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, AvatarGroup, Badge, Button, Card, Carousel, DatePicker, ProgressBar, Select } from 'primevue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';

import  'swiper/css';
</script>

<template>
    <AuthenticatedLayout title="Teacher Dashboard" :crumb="[{label:'Dashboard'}]">
        <!-- Greeting Section -->
        <Card class="bg-blue-400 bg-03">
            <template #content>
                <h1 class="text-white mb-1 leading-tight"> Good Morning Ms.Teena</h1>
                <p class="text-white mb-2">Have a Good day at work</p>
                <p class="text-surface-100">Notice : There is a staff meeting at 9AM today, Dont forget to
                    Attend!!!
                </p>
            </template>
        </Card>
        <!-- /Greeting Section -->

        <!-- Teacher-Profile -->
        <div class="grid xxl:grid-cols-12 gap-4 mt-4">
            <div class="xxl:col-span-8">
                <div class="grid grid-cols-12 gap-x-4">
                    <div class="xxl:col-span-7 xl:col-span-8 col-span-12">
                        <Card class="card bg-dark relative">
                            <template #content>
                                <div class="sm:flex items-center justify-between gap-x-3">
                                    <div class="flex items-center overflow-hidden mb-3">
                                        <Avatar image="assets/img/teachers/teacher-05.jpg" class="size-20 border-2" />
                                        <div class="overflow-hidden ml-3">
                                            <Badge value="#T594651" />
                                            <h3 class="text-white mb-1 text-truncate">Henriques Morgan </h3>
                                            <div class="flex items-center flex-wrap text-light row-gap-2">
                                                <span class="mr-2">Classes : I-A, V-B</span>
                                                <span class="flex items-center"><i class="ti ti-circle-filled text-yellow-400 mr-1"></i>Physics</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Button :as="Link" href="edit-teacher" class="">Edit Profile</Button>
                                </div>
                                <div class="student-card-bg">
                                    <img class="absolute top-0 left-[80%]" src="assets/img/bg/circle-shape.png" alt="Bg">
                                    <img class="absolute bottom-0 left-[15%]" src="assets/img/bg/shape-02.png" alt="Bg">
                                    <img class="absolute top-0 left-[60%]" src="assets/img/bg/shape-04.png" alt="Bg">
                                    <img class="absolute bottom-0 right-[20%]" src="assets/img/bg/blue-polygon.png" alt="Bg">
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="xxl:col-span-5 xl:col-span-4 col-span-12">
                        <Card class="">
                            <template #title>
                                <h4 class="">Syllabus</h4>
                            </template>
                            <template #content>
                                <Charts
                                    :data="{
                                        labels: ['completed', 'pending'],
                                        datasets: [
                                            {
                                                backgroundColor: [
                                                    'rgba(255, 99, 132, 0.5)',
                                                    'rgba(54, 162, 235, 0.6)'
                                                ],
                                                data: [90, 10]
                                            }
                                        ]
                                    }"
                                    type="doughnut"
                                    :options="{
                                        plugins:{
                                            label:false
                                        }
                                    }"
                                />
                            </template>
                        </Card>
                    </div>
                </div>

                <!-- Today's Class -->
                <Card class="mt-4">
                    <template #title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <h4 class="mr-2">Today's Class</h4>
                        </div>
                        <div class="inline-flex items-center class-datepick">
                            <DatePicker placeholder="16 may 2024" siz="small" />
                        </div>
                    </div>
                    </template>
                    <template #content>
                        <!-- TODO replace with another slider -->
                        <Carousel :value="[0, 2,3,4,5,6]" :num-visible="4">
                            <template #item="slotProps">
                                <div class="item">
                                    <div class="bg-teal-400/40 rounded p-3">
                                        <Badge severity="danger" size="large">
                                            <i class="ti ti-clock mr-1"></i>09:00 - 09:45
                                        </Badge>
                                        <p class="text-color dark:text-dark">Class V, B</p>
                                    </div>
                                </div>
                            </template>
                        </Carousel>
                    </template>
                </Card>
                <!-- /Today's Class -->

                <div class="grid md:grid-cols-2 grid-cols-1 gap-4 mt-4">

                    <!-- Attendance -->
                    <Card class="card">
                        <template #content>
                            <div class="bg-surface-200/50 rounded-lg border p-3 mb-3">
                                <div class="flex items-center justify-between flex-wrap">
                                    <h6 class="mb-2">Last 7 Days </h6>
                                    <p class="mb-2">14 May 2024 - 21 May 2024</p>
                                </div>
                                <div class="flex items-center gap-1 flex-wrap">
                                    <Badge v-for="i in ['M','T','W','TH','F','ST','S']" :value="i" severity="success" />
                                </div>
                            </div>
                            <p class="mb-3"><i class="ti ti-calendar-heart text-primary mr-2"></i>No of
                                total working days <span class="font-medium text-dark"> 28 Days</span></p>
                            <div class="border rounded p-3">
                                <div class="flex flex-wrap gap-x-1 justify-around flex-1 divide-x">
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
                                    <div class="col text-center flex-1">
                                        <p class="mb-1">Late</p>
                                        <h5>1</h5>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template #title>
                            <div class="flex items-center justify-between">
                            <h4 class="card-title">Attendance</h4>
                            <Select size="small" :options="filterByTimeOptions" :model-value="filterByTimeOptions[0]" >
                                <template #value="slotProps">
                                    <i class="ti ti-calendar-due"></i>{{ slotProps.value?.label ?? ""}}
                                </template>
                            </Select>
                        </div>
                        </template>
                    </Card>
                    <!-- /Attendance -->

                    <!-- Best Performers -->
                    <div class="flex flex-col gap-y-4">
                        <Card class="card">
                            <template #title>
                                <div class="card-header flex items-center justify-between">
                                    <h4 class="card-title">Best Performers</h4>
                                    <Button label="View All" :as="Link" href="#" variant="link" size="small"  />
                                </div>
                            </template>
                            <template #content>
                                <div class="sm:flex items-center mb-1">
                                    <div class="w-1/2 md:w-1/3 mb-2">
                                        <h6>Class IV, C</h6>
                                    </div>
                                    <ProgressBar class="flex-1" :value="80">
                                        <AvatarGroup>
                                            <Avatar shape="circle" v-for="i in 3" :image="`assets/img/students/student-0${i}.jpg`" />
                                        </AvatarGroup>
                                    </ProgressBar>
                                </div>
                            </template>
                        </Card>
                        <Card class="card flex-1">
                            <template #title>
                                <div class="card-header flex items-center justify-between">
                                <h4 class="card-title">Student Progress</h4>
                                <Select :options="filterByTimeOptions" size="small" :modelValue="filterByTimeOptions[0]">>
                                  <template #value="slotProps">
                                    <i class="ti ti-calendar mr-2"></i>{{ slotProps.value.label }}
                                  </template>
                                </Select>
                            </div>
                            </template>
                            <template #content>
                                <div v-for="i in 3" class="flex items-center justify-between p-3 mb-2 border rounded-xl">
                                    <div class="flex items-center overflow-hidden mr-2">
                                        <Avatar :pt="{image:{class:'rounded-xl'}}" class="rounded-xl" image="assets/img/students/student-09.jpg" size="large" />
                                        <div class="overflow-hidden ml-2">
                                            <h6 class="mb-1 text-truncate"><a href="javascript:void(0);">Susan
                                                    Boswell</a></h6>
                                            <p>III, B</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <img src="assets/img/icons/medal.svg" alt="icon">
                                        <span class="badge badge-success ml-2">98%</span>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <!-- /Best Performers -->

                </div>
            </div>

            <!-- Schedules -->
            <div class="xxl:col-span-4">
                <Card class="card flex-fill">

                    <div class="card-body">
                    </div>
                    <template #content>
                        <DatePicker fluid inline />
                        <h4 class="mb-3">Upcoming Events</h4>
                        <div class="h-[495px] overflow-hidden relative">
                            <PerfectScrollbar class="h-[495px]">
                                <!-- Event Item -->
                                <div v-for="i in 4" class="border-red-400 border-l-4 shadow-sm p-3 mb-3">
                                    <div class="flex items-center mb-3 pb-3 border-b">
                                        <Avatar :pt="{root:{class:'bg-red-200'}, icon: {class:'text-red-600'}}" icon="ti ti-vacuum-cleaner" class="" size="large" />
                                        <div class="flex-1 ml-2">
                                            <h6 class="mb-1">Vacation Meeting</h6>
                                            <p class="flex items-center"><i class="ti ti-calendar mr-1"></i>07 July 2024 - 07 July 2024</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="mb-0"><i class="ti ti-clock mr-1"></i>09:10 AM - 10:50 PM</p>
                                        <AvatarGroup>
                                            <Avatar v-for="i in [11, 13]" :image="`assets/img/parents/parent-${i}.jpg`" shape="circle" />
                                        </AvatarGroup>
                                    </div>
                                </div>
                                <!-- /Event Item -->
                            </PerfectScrollbar>
                        </div>
                    </template>
                    <template #title>
                        <div class="card-header flex items-center justify-between">
                            <h4 class="card-title">Schedules</h4>
                            <Button icon="ti ti-square-plus" label="Add New" variant="link" size="small" />
                        </div>
                    </template>
                </Card>
            </div>
            <!-- /Schedules -->
        </div>
        <!-- Teacher-profile -->

        <!-- Syllabus -->>
        <Card class="card">
            <template #content>

                <Swiper :slides-per-view="4" :space-between="10">
                    <SwiperSlide class="item" v-for="i in 5">
                        <Card class="card mb-0">
                            <template #content>
                                <div class="bg-green-200/40 rounded p-2 font-semibold mb-3 text-center">
                                    Class V, B</div>
                                <div class="border-b mb-3">
                                    <h5 class="mb-3">Introduction Note to Physics on Tech</h5>
                                    <ProgressBar :value="80" class="h-2" :show-value="false" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <Button label="Reschedule" icon="ti ti-edit" variant="text" size="small" :as="Link" href="#" severity="secondary" />
                                    <Button label="Details" icon="ti ti-info-circle" variant="link" size="small" :as="Link" href="#" />
                                </div>
                            </template>
                        </Card>
                    </SwiperSlide>
                </Swiper>
            </template>
            <template #title> <div class="flex items-center justify-between">
                <h4 class="card-title">Syllabus / Lesson Plan</h4>
                <Button variant="link" label="View All" size="small" :as="Link" href="#" />
            </div></template>
        </Card>
        <!-- /Syllabus -->
    </AuthenticatedLayout>
</template>
