<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FullCalendarComponent from '@fullcalendar/vue3';
import dayGridPlugin from "@fullcalendar/daygrid"
import { Avatar, AvatarGroup, Card, Select } from 'primevue';

const $ = Date;

</script>

<template>
    <AuthenticatedLayout title="Events"
        :crumb="[{ label: 'Dashboard' }, { label: 'Announcement' }, { label: 'Events' }]">

        <div class="grid grid-cols-12">

            <!-- Event Calendar -->
            <div class="xl:col-span-8 xxl:col-span-9 col-span-12 theiaStickySidebar">
                <Card>
                    <template #content>
                        <FullCalendarComponent :options="{
                            plugins: [dayGridPlugin],
                            initialView: 'dayGridMonth',
                            events: [
                                {
                                    title: 'Summer Vacation',
                                    className: 'progress-danger',
                                    backgroundColor: '#FDE9ED',
                                    start: new Date($.now() - 168000000).toJSON().slice(0, 10),
                                    textColor: '#FF0000'
                                },
                                {
                                    title: 'Parents, Teacher Meet',
                                    className: 'progress-info',
                                    backgroundColor: '#E7F1FC',
                                    start: new Date($.now() + 338000000).toJSON().slice(0, 10),
                                    end: new Date($.now() + 338040000).toJSON().slice(0, 10)
                                },
                                {
                                    title: 'Admission Camp',
                                    className: 'progress-pending',
                                    backgroundColor: '#E6F9FF',
                                    start: new Date($.now() - 338000000).toJSON().slice(0, 10)
                                },
                                {
                                    title: 'Activity -  Training',
                                    className: 'progress-success',
                                    backgroundColor: '#E8F9E8',
                                    start: new Date($.now() + 68000000).toJSON().slice(0, 10)
                                }
                            ],
                            headerToolbar: {
                                start: 'title',
                                center: 'dayGridMonth,dayGridWeek,dayGridDay',
                                end: 'custombtn'
                            },
                            customButtons: {
                                custombtn: {
                                    text: 'Add New Event',
                                    click: function () {
                                        // var myModal = new bootstrap.Modal(document.getElementById('add_event'));
                                        // myModal.show();
                                        console.log('clicked');

                                    }
                                }
                            }
                        }" />
                    </template>
                </Card>
            </div>
            <!-- /Event Calendar -->

            <!-- Event List -->
            <div class="xl:col-span-4 xxl:col-span-3 col-span-12">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="">Events</h5>
                    <Select fluid :options="['All Category', 'Celebration', 'Training', 'Meeting', 'Holidays', 'Camp']" model-value="All Category" />
                </div>

                <!-- Event Item -->
                <div v-for="i in 2" class="border-blue-300 border-l-4 shadow-sm p-3 mb-3 bg-white">
                    <div class="flex items-center mb-3 pb-3 border-bottom">
                        <Avatar icon="ti ti-users-group" class="bg-primary-300/50 mr-2"></Avatar>
                        <div class="flex-1">
                            <h6 class="">Parents, Teacher Meet</h6>
                            <p class="text-xs"><i class="ti ti-calendar mr-1"></i>15 July 2024</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="mb-0 text-xs"><i class="ti ti-clock mr-1"></i>09:10AM - 10:50PM</p>
                        <AvatarGroup>
                            <Avatar image="assets/img/parents/parent-01.jpg" shape="circle" />
                            <Avatar image="assets/img/parents/parent-02.jpg" shape="circle" />
                            <Avatar image="assets/img/parents/parent-03.jpg" shape="circle" />
                        </AvatarGroup>
                    </div>
                </div>
                <!-- /Event Item -->

                <!-- Event Item -->
                <div v-for="i in 2" class="border-l-4 border-red-400 shadow-sm p-3 mb-3 bg-white">
                    <div class="flex items-center mb-3 pb-3 border-bottom">
                        <Avatar icon="ti ti-vacuum-cleaner" class="bg-red-300/50 mr-2"></Avatar>
                        <div class="flex-1">
                            <h6 class="mb-1">Summer Vacation</h6>
                            <p class="text-xs"><i class="ti ti-calendar mr-1"></i>07 July 2024 - 08 July 2024</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs mb-0"><i class="ti ti-clock mr-1"></i>09:10 AM - 10:50 PM</p>
                        <AvatarGroup>
                            <Avatar image="assets/img/parents/parent-04.jpg" shape="circle" />
                            <Avatar image="assets/img/parents/parent-05.jpg" shape="circle" />
                        </AvatarGroup>
                    </div>
                </div>
                <!-- /Event Item -->

            </div>
            <!-- /Event List -->

        </div>
    </AuthenticatedLayout>
</template>
