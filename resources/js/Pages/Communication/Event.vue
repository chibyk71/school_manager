<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FullCalendarComponent from '@fullcalendar/vue3';
import { Card } from 'primevue';

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
                            events: [
                                {
                                    title: 'Summer Vacation',
                                    className: 'progress-danger',
                                    backgroundColor: '#FDE9ED',
                                    start: new Date($.now() - 168000000).toJSON().slice(0, 10)
                                },
                                {
                                    title: 'Parents, Teacher Meet',
                                    className: 'progress-info',
                                    backgroundColor: '#E7F1FC',
                                    start: new Date($.now() + 338000000).toJSON().slice(0, 10)
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
            <div class="col-xl-4 xxl:col-span-3 col-span-12 theiaStickySidebar">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-3">Events</h5>
                    <div class="dropdown mb-3">
                        <a href="javascript:void(0);" class="btn btn-outline-light dropdown-toggle"
                            data-bs-toggle="dropdown">All
                            Category
                        </a>
                        <ul class="dropdown-menu p-3">
                            <li>
                                <a href="events.html#" class="dropdown-item rounded-1 d-flex align-items-center">
                                    <i class="ti ti-circle-filled fs-8 text-warning me-2"></i>Celebration
                                </a>
                            </li>
                            <li>
                                <a href="events.html#" class="dropdown-item rounded-1 d-flex align-items-center">
                                    <i class="ti ti-circle-filled fs-8 text-success me-2"></i>Training
                                </a>
                            </li>
                            <li>
                                <a href="events.html#" class="dropdown-item rounded-1 d-flex align-items-center">
                                    <i class="ti ti-circle-filled fs-8 text-info me-2"></i>Meeting
                                </a>
                            </li>
                            <li>
                                <a href="events.html#" class="dropdown-item rounded-1 d-flex align-items-center">
                                    <i class="ti ti-circle-filled fs-8 text-danger me-2"></i>Holidays
                                </a>
                            </li>
                            <li>
                                <a href="events.html#" class="dropdown-item rounded-1 d-flex align-items-center">
                                    <i class="ti ti-circle-filled fs-8 text-pending me-2"></i>Camp
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Event Item -->
                <div class="border-start border-info border-3 shadow-sm p-3 mb-3 bg-white">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <span class="avatar p-1 me-3 bg-primary-transparent flex-shrink-0">
                            <i class="ti ti-users-group text-info fs-20"></i>
                        </span>
                        <div class="flex-fill">
                            <h6 class="mb-1">Parents, Teacher Meet</h6>
                            <p class="fs-12"><i class="ti ti-calendar me-1"></i>15 July 2024</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="mb-0 fs-12"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-01.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-07.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-02.jpg" class="rounded" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
                <!-- /Event Item -->

                <!-- Event Item -->
                <div class="border-start border-danger border-3 shadow-sm p-3 mb-3 bg-white">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <span class="avatar p-1 me-2 bg-danger-transparent flex-shrink-0">
                            <i class="ti ti-vacuum-cleaner fs-24"></i>
                        </span>
                        <div class="flex-fill">
                            <h6 class="mb-1">Summer Vacation</h6>
                            <p class="fs-12"><i class="ti ti-calendar me-1"></i>07 July 2024 - 08 July 2024</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="fs-12 mb-0"><i class="ti ti-clock me-1"></i>09:10 AM - 10:50 PM</p>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-11.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-13.jpg" class="rounded" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
                <!-- /Event Item -->

                <!-- Event Item -->
                <div class="border-start border-info border-3 shadow-sm p-3 mb-3 bg-white">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <span class="avatar p-1 me-2 bg-info-transparent flex-shrink-0">
                            <i class="ti ti-user-edit fs-20"></i>
                        </span>
                        <div class="flex-fill">
                            <h6 class="mb-1">Staff Meeting</h6>
                            <p class="fs-12"><i class="ti ti-calendar me-1"></i>10 July 2024</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="fs-12 mb-0"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-05.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-06.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-07.jpg" class="rounded" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
                <!-- /Event Item -->

                <!-- Event Item -->
                <div class="border-start border-secondary border-3 shadow-sm p-3 mb-3 bg-white">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <span class="avatar p-1 me-2 bg-secondary-transparent flex-shrink-0">
                            <i class="ti ti-campfire fs-20"></i>
                        </span>
                        <div class="flex-fill">
                            <h6 class="mb-1">Admission Camp</h6>
                            <p class="fs-12"><i class="ti ti-calendar me-1"></i>10 July 2024</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="fs-12 mb-0"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-04.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-05.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/parents/parent-10.jpg" class="rounded" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
                <!-- /Event Item -->

                <!-- Event Item -->
                <div class="border-start border-success border-3 shadow-sm p-3 mb-4 bg-white">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <span class="avatar p-1 me-2 bg-success-transparent flex-shrink-0">
                            <i class="ti ti-clipboard-heart fs-20"></i>
                        </span>
                        <div class="flex-fill">
                            <h6 class="mb-1">Activity Training</h6>
                            <p class="fs-12"><i class="ti ti-calendar me-1"></i>26 July 2024</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="fs-12 mb-0"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar border-0">
                                <img src="assets/img/teachers/teacher-02.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/teachers/teacher-05.jpg" class="rounded" alt="img">
                            </span>
                            <span class="avatar border-0">
                                <img src="assets/img/teachers/teacher-06.jpg" class="rounded" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
                <!-- /Event Item -->

            </div>
            <!-- /Event List -->

        </div>
    </AuthenticatedLayout>
</template>
