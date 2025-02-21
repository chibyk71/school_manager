<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="Dashboard" :crumb="[{label:'Dashboard'}]" :buttons="[{label:'Add Student',icon:'pi pi-plus'}]">

        <div class="row">
            <div class="col-md-12">
                <div class="alert-message">
                    <div class="alert alert-success rounded-pill d-flex align-items-center justify-content-between border-success mb-4"
                        role="alert">
                        <div class="d-flex align-items-center">
                            <span class="me-1 avatar avatar-sm flex-shrink-0"><img
                                    src="assets/img/profiles/avatar-27.jpg" alt="Img"
                                    class="img-fluid rounded-circle"></span>
                            <p>Fahed III,C has paid Fees for the <strong class="mx-1">“Term1”</strong></p>
                        </div>
                        <button type="button" class="btn-close p-0" data-bs-dismiss="alert"
                            aria-label="Close"><span><i class="ti ti-x"></i></span></button>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="card bg-dark">
                    <div class="overlay-img">
                        <img src="assets/img/bg/shape-04.png" alt="img" class="img-fluid shape-01">
                        <img src="assets/img/bg/shape-01.png" alt="img" class="img-fluid shape-02">
                        <img src="assets/img/bg/shape-02.png" alt="img" class="img-fluid shape-03">
                        <img src="assets/img/bg/shape-03.png" alt="img" class="img-fluid shape-04">
                    </div>
                    <div class="card-body">
                        <div
                            class="d-flex align-items-xl-center justify-content-xl-between flex-xl-row flex-column">
                            <div class="mb-3 mb-xl-0">
                                <div class="d-flex align-items-center flex-wrap mb-2">
                                    <h1 class="text-white me-2">Welcome Back, Mr. Herald</h1>
                                    <a href="profile.html"
                                        class="avatar avatar-sm img-rounded bg-gray-800 dark-hover"><i
                                            class="ti ti-edit text-white"></i></a>
                                </div>
                                <p class="text-white">Have a Good day at work</p>
                            </div>
                            <p class="text-white"><i class="ti ti-refresh me-1"></i>Updated Recently on 15 Jun
                                2024</p>
                        </div>
                    </div>
                </div>
                <!-- /Dashboard Content -->

            </div>
        </div>
        <div class="row">
            <!--Total Students -->
            <div class="col-xxl-3 col-sm-6 d-flex">
                <div class="card flex-1 animate-card border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-xl bg-danger-transparent me-2 p-1">
                                <img src="assets/img/icons/student.svg" alt="img">
                            </div>
                            <div class="overflow-hidden flex-fill">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="counter">3654</h2>
                                    <span class="badge bg-danger">1.2%</span>
                                </div>
                                <p>Total Students</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top mt-3 pt-3">
                            <p class="mb-0">Active : <span class="text-dark fw-semibold">3643</span></p>
                            <span class="text-light">|</span>
                            <p>Inactive : <span class="text-dark fw-semibold">11</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Students -->

            <!-- Total Teachers -->
            <div class="col-xxl-3 col-sm-6 d-flex">
                <div class="card flex-fill animate-card border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-xl me-2 bg-secondary-transparent p-1">
                                <img src="assets/img/icons/teacher.svg" alt="img">
                            </div>
                            <div class="overflow-hidden flex-fill">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="counter">284</h2>
                                    <span class="badge bg-skyblue">1.2%</span>
                                </div>
                                <p>Total Teachers</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top mt-3 pt-3">
                            <p class="mb-0">Active : <span class="text-dark fw-semibold">254</span></p>
                            <span class="text-light">|</span>
                            <p>Inactive : <span class="text-dark fw-semibold">30</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Teachers -->

            <!-- Total Staff -->
            <div class="col-xxl-3 col-sm-6 d-flex">
                <div class="card flex-fill animate-card border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-xl me-2 bg-warning-transparent p-1">
                                <img src="assets/img/icons/staff.svg" alt="img">
                            </div>
                            <div class="overflow-hidden flex-fill">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="counter">162</h2>
                                    <span class="badge bg-warning">1.2%</span>
                                </div>
                                <p>Total Staff</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top mt-3 pt-3">
                            <p class="mb-0">Active : <span class="text-dark fw-semibold">161</span></p>
                            <span class="text-light">|</span>
                            <p>Inactive : <span class="text-dark fw-semibold">02</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Staff -->

            <!-- Total Subjects -->
            <div class="col-xxl-3 col-sm-6 d-flex">
                <div class="card flex-fill animate-card border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-xl me-2 bg-success-transparent p-1">
                                <img src="assets/img/icons/subject.svg" alt="img">
                            </div>
                            <div class="overflow-hidden flex-fill">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="counter">82</h2>
                                    <span class="badge bg-success">1.2%</span>
                                </div>
                                <p>Total Subjects</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top mt-3 pt-3">
                            <p class="mb-0">Active : <span class="text-dark fw-semibold">81</span></p>
                            <span class="text-light">|</span>
                            <p>Inactive : <span class="text-dark fw-semibold">01</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Subjects -->

        </div>

        <div class="row">

            <!-- Schedules -->
            <div class="col-xxl-4 col-xl-6 col-md-12 d-flex">
                <div class="card flex-fill">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="card-title">Schedules</h4>
                        </div>
                        <a href="index.html#" class="link-primary fw-medium me-2" data-bs-toggle="modal"
                            data-bs-target="#add_event"><i class="ti ti-square-plus me-1"></i>Add New</a>
                    </div>
                    <div class="card-body">
                        <div class="datepic mb-4"></div>
                        <h5 class="mb-3">Upcoming Events</h5>
                        <div class="event-wrapper event-scroll">
                            <!-- Event Item -->
                            <div class="border-start border-skyblue border-3 shadow-sm p-3 mb-3">
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <span class="avatar p-1 me-2 bg-teal-transparent flex-shrink-0">
                                        <i class="ti ti-user-edit text-info fs-20"></i>
                                    </span>
                                    <div class="flex-fill">
                                        <h6 class="mb-1">Parents, Teacher Meet</h6>
                                        <p class="d-flex align-items-center"><i
                                                class="ti ti-calendar me-1"></i>15 July 2024</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <p class="mb-0"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                                    <div class="avatar-list-stacked avatar-group-sm">
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-01.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-07.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-02.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- /Event Item -->

                            <!-- Event Item -->
                            <div class="border-start border-info border-3 shadow-sm p-3 mb-3">
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <span class="avatar p-1 me-2 bg-info-transparent flex-shrink-0">
                                        <i class="ti ti-user-edit fs-20"></i>
                                    </span>
                                    <div class="flex-fill">
                                        <h6 class="mb-1">Parents, Teacher Meet</h6>
                                        <p class="d-flex align-items-center"><i
                                                class="ti ti-calendar me-1"></i>15 July 2024</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <p class="mb-0"><i class="ti ti-clock me-1"></i>09:10AM - 10:50PM</p>
                                    <div class="avatar-list-stacked avatar-group-sm">
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-05.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-06.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-07.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- /Event Item -->

                            <!-- Event Item -->
                            <div class="border-start border-danger border-3 shadow-sm p-3 mb-3">
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <span class="avatar p-1 me-2 bg-danger-transparent flex-shrink-0">
                                        <i class="ti ti-vacuum-cleaner fs-24"></i>
                                    </span>
                                    <div class="flex-fill">
                                        <h6 class="mb-1">Vacation Meeting</h6>
                                        <p class="d-flex align-items-center"><i
                                                class="ti ti-calendar me-1"></i>07 July 2024 - 07 July 2024</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <p class="mb-0"><i class="ti ti-clock me-1"></i>09:10 AM - 10:50 PM</p>
                                    <div class="avatar-list-stacked avatar-group-sm">
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-11.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                        <span class="avatar border-0">
                                            <img src="assets/img/parents/parent-13.jpg" class="rounded-circle"
                                                alt="img">
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- /Event Item -->

                        </div>
                    </div>
                </div>
            </div>
            <!-- /Schedules -->

            <!-- Attendance -->
            <div class="col-xxl-4 col-xl-6 col-md-12 d-flex flex-column">

                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Attendance</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-calendar-due me-1"></i>Today
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Week
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-tab mb-4">
                            <ul class="nav">
                                <li>
                                    <a href="index.html#" class="active" data-bs-toggle="tab"
                                        data-bs-target="#students">Students</a>
                                </li>
                                <li>
                                    <a href="index.html#" data-bs-toggle="tab" data-bs-target="#teachers">Teachers</a>
                                </li>
                                <li>
                                    <a href="index.html#" data-bs-toggle="tab" data-bs-target="#staff">Staff</a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content">
                            <div class="tab-pane fade active show" id="students">
                                <div class="row gx-3">
                                    <div class="col-sm-4">
                                        <div class="card bg-light-300 shadow-none border-0">
                                            <div class="card-body p-3 text-center">
                                                <h5>28</h5>
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
                                                <h5>01</h5>
                                                <p class="fs-12">Late</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div id="student-chart" class="mb-4"></div>
                                    <a href="student-attendance.html" class="btn btn-light"><i
                                            class="ti ti-calendar-share me-1"></i>View All</a>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="teachers">
                                <div class="row gx-3">
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
                                            class="ti ti-calendar-share me-1"></i>View All</a>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="staff">
                                <div class="row gx-3">
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
                                            class="ti ti-calendar-share me-1"></i>View All</a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row flex-fill">

                    <!-- Best Performer -->
                    <div class="col-sm-6 d-flex flex-column">
                        <div class="bg-success-800 p-3 br-5 text-center flex-fill mb-4 pb-0  owl-height bg-01">
                            <div class="owl-carousel student-slider h-100">
                                <div class="item h-100">
                                    <div class="d-flex justify-content-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Best Performer</h5>
                                            <h4 class="mb-1 text-white">Rubell</h4>
                                            <p class="text-light">Physics Teacher</p>
                                        </div>
                                        <img src="assets/img/performer/performer-01.png" alt="img">
                                    </div>
                                </div>
                                <div class="item h-100">
                                    <div class="d-flex justify-content-between flex-column h-100">
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
                    <div class="col-sm-6 d-flex flex-column">
                        <div class="bg-info p-3 br-5 text-center flex-fill mb-4 pb-0 owl-height bg-02">
                            <div class="owl-carousel teacher-slider h-100">
                                <div class="item h-100">
                                    <div class="d-flex justify-content-between flex-column h-100">
                                        <div>
                                            <h5 class="mb-3 text-white">Star Students</h5>
                                            <h4 class="mb-1 text-white">Tenesa</h4>
                                            <p class="text-light">XII, A</p>
                                        </div>
                                        <img src="assets/img/performer/student-performer-01.png" alt="img">
                                    </div>
                                </div>
                                <div class="item h-100">
                                    <div class="d-flex justify-content-between flex-column h-100">
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

            <div class="col-xxl-4 col-md-12 d-flex flex-column">

                <!-- Quick Links -->
                <div class="card flex-fill">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Quick Links</h4>
                    </div>
                    <div class="card-body pb-1">
                        <div class="owl-carousel link-slider">
                            <div class="item">
                                <a href="class-time-table.html"
                                    class="d-block bg-success-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div class="avatar avatar-lg border p-1 border-success rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-success rounded-circle"><i
                                                class="ti ti-calendar"></i></span>
                                    </div>
                                    <p class="text-dark">Calendar</p>
                                </a>
                                <a href="fees-group.html"
                                    class="d-block bg-secondary-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div
                                        class="avatar avatar-lg border p-1 border-secondary rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-secondary rounded-circle"><i
                                                class="ti ti-license"></i></span>
                                    </div>
                                    <p class="text-dark">Fees</p>
                                </a>
                            </div>
                            <div class="item">
                                <a href="exam-results.html"
                                    class="d-block bg-primary-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div class="avatar avatar-lg border p-1 border-primary rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-primary rounded-circle"><i
                                                class="ti ti-hexagonal-prism"></i></span>
                                    </div>
                                    <p class="text-dark">Exam Result</p>
                                </a>
                                <a href="class-home-work.html"
                                    class="d-block bg-danger-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div class="avatar avatar-lg border p-1 border-danger rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-danger rounded-circle"><i
                                                class="ti ti-report-money"></i></span>
                                    </div>
                                    <p class="text-dark">Home Works</p>
                                </a>
                            </div>
                            <div class="item">
                                <a href="student-attendance.html"
                                    class="d-block bg-warning-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div class="avatar avatar-lg border p-1 border-warning rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-warning rounded-circle"><i
                                                class="ti ti-calendar-share"></i></span>
                                    </div>
                                    <p class="text-dark">Attendance</p>
                                </a>
                                <a href="attendance-report.html"
                                    class="d-block bg-skyblue-transparent ronded p-2 text-center mb-3 class-hover">
                                    <div class="avatar avatar-lg border p-1 border-skyblue rounded-circle mb-2">
                                        <span
                                            class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-skyblue rounded-circle"><i
                                                class="ti ti-file-pencil"></i></span>
                                    </div>
                                    <p class="text-dark">Reports</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Quick Links -->

                <!-- Class Routine -->
                <div class="card flex-fill">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Class Routine</h4>
                        <a href="index.html#" class="link-primary fw-medium" data-bs-toggle="modal"
                            data-bs-target="#add_class_routine"><i class="ti ti-square-plus me-1"></i>Add
                            New</a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center rounded border p-3 mb-3">
                            <span class="avatar avatar-md flex-shrink-0 border rounded me-2">
                                <img src="assets/img/teachers/teacher-01.jpg" class="rounded" alt="Profile">
                            </span>
                            <div class="w-100">
                                <p class="mb-1">Oct 2024</p>
                                <div class="progress progress-xs  flex-grow-1 mb-1">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary rounded"
                                        role="progressbar" style="width: 80%;" aria-valuenow="80"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center rounded border p-3 mb-3">
                            <span class="avatar avatar-md flex-shrink-0 border rounded me-2">
                                <img src="assets/img/teachers/teacher-02.jpg" class="rounded" alt="Profile">
                            </span>
                            <div class="w-100">
                                <p class="mb-1">Nov 2024</p>
                                <div class="progress progress-xs  flex-grow-1 mb-1">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning rounded"
                                        role="progressbar" style="width: 80%;" aria-valuenow="80"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center rounded border p-3 mb-0">
                            <span class="avatar avatar-md flex-shrink-0 border rounded me-2">
                                <img src="assets/img/teachers/teacher-03.jpg" class="rounded" alt="Profile">
                            </span>
                            <div class="w-100">
                                <p class="mb-1">Oct 2024</p>
                                <div class="progress progress-xs  flex-grow-1 mb-1">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success rounded"
                                        role="progressbar" style="width: 80%;" aria-valuenow="80"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Class Routine -->

                <!-- Class Wise Performance -->
                <div class="card flex-fill">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Performance</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-school-bell  me-2"></i>Class II
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class I
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class II
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class III
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class IV
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-md-flex align-items-center justify-content-between">
                            <div class="me-md-3 mb-3 mb-md-0 w-100">
                                <div
                                    class="border border-dashed p-3 rounded d-flex align-items-center justify-content-between mb-1">
                                    <p class="mb-0 me-2"><i
                                            class="ti ti-arrow-badge-down-filled me-2 text-primary"></i>Top</p>
                                    <h5>45</h5>
                                </div>
                                <div
                                    class="border border-dashed p-3 rounde d-flex align-items-center justify-content-between mb-1">
                                    <p class="mb-0 me-2"><i
                                            class="ti ti-arrow-badge-down-filled me-2 text-warning"></i>Average
                                    </p>
                                    <h5>11</h5>
                                </div>
                                <div
                                    class="border border-dashed p-3 rounded d-flex align-items-center justify-content-between mb-0">
                                    <p class="mb-0 me-2"><i
                                            class="ti ti-arrow-badge-down-filled me-2 text-danger"></i>Below Avg
                                    </p>
                                    <h5>02</h5>
                                </div>
                            </div>
                            <div id="class-chart" class="text-center text-md-left"></div>
                        </div>
                    </div>
                </div>
                <!-- /Class Wise Performance -->

            </div>

        </div>

        <div class="row">

            <!-- Fees Collection -->
            <div class="col-xxl-8 col-xl-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Fees Collection</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-calendar  me-2"></i>Last 8 Quater
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Month
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Year
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last 12 Quater
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last 16 Quater
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body pb-0">
                        <div id="fees-chart"></div>
                    </div>
                </div>
            </div>
            <!-- /Fees Collection -->

            <!-- Leave Requests -->
            <div class="col-xxl-4 col-xl-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Leave Requests</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-calendar-due me-1"></i>Today
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Week
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card mb-2">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <a href="javascript:void(0);"
                                            class="avatar avatar-lg flex-shrink-0 me-2">
                                            <img src="assets/img/profiles/avatar-14.jpg" alt="student">
                                        </a>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate"><a
                                                    href="javascript:void(0);">James</a><span
                                                    class="badge badge-soft-danger ms-1">Emergency</span></h6>
                                            <p class="text-truncate">Physics Teacher</p>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <a href="index.html#" class="avatar avatar-xs p-0 btn btn-success me-1"><i
                                                class="ti ti-checks"></i></a>
                                        <a href="index.html#" class="avatar avatar-xs p-0 btn btn-danger"><i
                                                class="ti ti-x"></i></a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between border-top pt-3">
                                    <p class="mb-0">Leave : <span class="fw-semibold">12 -13 May</span></p>
                                    <p>Apply on : <span class="fw-semibold">12 May</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-0">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <a href="javascript:void(0);"
                                            class="avatar avatar-lg flex-shrink-0 me-2">
                                            <img src="assets/img/profiles/avatar-19.jpg" alt="student">
                                        </a>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate "><a
                                                    href="javascript:void(0);">Ramien</a><span
                                                    class="badge badge-soft-warning ms-1">Casual</span></h6>
                                            <p class="text-truncate">Accountant</p>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <a href="index.html#" class="avatar avatar-xs p-0 btn btn-success me-1"><i
                                                class="ti ti-checks"></i></a>
                                        <a href="index.html#" class="avatar avatar-xs p-0 btn btn-danger"><i
                                                class="ti ti-x"></i></a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between border-top pt-3">
                                    <p class="mb-0">Leave : <span class="fw-semibold">12 -13 May</span></p>
                                    <p>Apply on : <span class="fw-semibold">11 May</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Leave Requests -->

        </div>

        <div class="row">

            <!-- Links -->
            <div class="col-xl-3 col-md-6 d-flex">
                <a href="student-attendance.html"
                    class="card bg-warning-transparent border border-5 border-white animate-card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-lg bg-warning rounded flex-shrink-0 me-2"><i
                                        class="ti ti-calendar-share fs-24"></i></span>
                                <div class="overflow-hidden">
                                    <h6 class="fw-semibold text-default">View Attendance</h6>
                                </div>
                            </div>
                            <span
                                class="btn btn-white warning-btn-hover avatar avatar-sm p-0 flex-shrink-0 rounded-circle"><i
                                    class="ti ti-chevron-right fs-14"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- /Links -->

            <!-- Links -->
            <div class="col-xl-3 col-md-6 d-flex">
                <a href="events.html"
                    class="card bg-success-transparent border border-5 border-white animate-card flex-fill ">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-lg bg-success rounded flex-shrink-0 me-2"><i
                                        class="ti ti-speakerphone fs-24"></i></span>
                                <div class="overflow-hidden">
                                    <h6 class="fw-semibold text-default">New Events</h6>
                                </div>
                            </div>
                            <span
                                class="btn btn-white success-btn-hover avatar avatar-sm p-0 flex-shrink-0 rounded-circle"><i
                                    class="ti ti-chevron-right fs-14"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- /Links -->

            <!-- Links -->
            <div class="col-xl-3 col-md-6 d-flex">
                <a href="membership-plans.html"
                    class="card bg-danger-transparent border border-5 border-white animate-card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-lg bg-danger rounded flex-shrink-0 me-2"><i
                                        class="ti ti-sphere fs-24"></i></span>
                                <div class="overflow-hidden">
                                    <h6 class="fw-semibold text-default">Membership Plans</h6>
                                </div>
                            </div>
                            <span
                                class="btn btn-white avatar avatar-sm p-0 flex-shrink-0 rounded-circle danger-btn-hover"><i
                                    class="ti ti-chevron-right fs-14"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- /Links -->

            <!-- Links -->
            <div class="col-xl-3 col-md-6 d-flex">
                <a href="student-attendance.html"
                    class="card bg-secondary-transparent border border-5 border-white animate-card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-lg bg-secondary rounded flex-shrink-0 me-2"><i
                                        class="ti ti-moneybag fs-24"></i></span>
                                <div class="overflow-hidden">
                                    <h6 class="fw-semibold text-default">Finance & Accounts</h6>
                                </div>
                            </div>
                            <span
                                class="btn btn-white secondary-btn-hover avatar avatar-sm p-0 flex-shrink-0 rounded-circle"><i
                                    class="ti ti-chevron-right fs-14"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- /Links -->

        </div>
        <div class="row">

            <!-- Total Earnings -->
            <div class="col-xxl-4 col-xl-6 d-flex flex-column">
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1">Total Earnings</h6>
                                <h2>$64,522,24</h2>
                            </div>
                            <span class="avatar avatar-lg bg-primary">
                                <i class="ti ti-user-dollar"></i>
                            </span>
                        </div>
                    </div>
                    <div id="total-earning"></div>
                </div>
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1">Total Expenses</h6>
                                <h2>$60,522,24</h2>
                            </div>
                            <span class="avatar avatar-lg bg-danger">
                                <i class="ti ti-user-dollar"></i>
                            </span>
                        </div>
                    </div>
                    <div id="total-expenses"></div>
                </div>
            </div>
            <!-- /Total Earnings -->

            <!-- Notice Board -->
            <div class="col-xxl-5 col-xl-12 order-3 order-xxl-2 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Notice Board</h4>
                        <a href="notice-board.html" class="fw-medium">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="notice-widget">
                            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden me-2 mb-2 mb-sm-0">
                                    <span
                                        class="bg-primary-transparent avatar avatar-md me-2 rounded-circle flex-shrink-0">
                                        <i class="ti ti-books fs-16"></i>
                                    </span>
                                    <div class="overflow-hidden">
                                        <h6 class="text-truncate mb-1">New Syllabus Instructions</h6>
                                        <p><i class="ti ti-calendar me-2"></i>Added on : 11 Mar 2024</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark"><i class="ti ti-clck me-1"></i>20
                                    Days</span>
                            </div>
                            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden me-2 mb-2 mb-sm-0">
                                    <span
                                        class="bg-success-transparent avatar avatar-md me-2 rounded-circle flex-shrink-0">
                                        <i class="ti ti-note fs-16"></i>
                                    </span>
                                    <div class="overflow-hidden">
                                        <h6 class="text-truncate mb-1">World Environment Day Program.....!!!
                                        </h6>
                                        <p><i class="ti ti-calendar me-2"></i>Added on : 21 Apr 2024</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark"><i class="ti ti-clck me-1"></i>15
                                    Days</span>
                            </div>
                            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden me-2 mb-2 mb-sm-0">
                                    <span
                                        class="bg-danger-transparent avatar avatar-md me-2 rounded-circle flex-shrink-0">
                                        <i class="ti ti-bell-check fs-16"></i>
                                    </span>
                                    <div class="overflow-hidden">
                                        <h6 class="text-truncate mb-1">Exam Preparation Notification!</h6>
                                        <p><i class="ti ti-calendar me-2"></i>Added on : 13 Mar 2024</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark"><i class="ti ti-clck me-1"></i>12
                                    Days</span>
                            </div>
                            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden me-2 mb-2 mb-sm-0">
                                    <span
                                        class="bg-skyblue-transparent avatar avatar-md me-2 rounded-circle flex-shrink-0">
                                        <i class="ti ti-notes fs-16"></i>
                                    </span>
                                    <div class="overflow-hidden">
                                        <h6 class="text-truncate mb-1">Online Classes Preparation</h6>
                                        <p><i class="ti ti-calendar me-2"></i>Added on : 24 May 2024</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark"><i class="ti ti-clck me-1"></i>02
                                    Days</span>
                            </div>
                            <div class="d-sm-flex align-items-center justify-content-between mb-0">
                                <div class="d-flex align-items-center overflow-hidden me-2 mb-2 mb-sm-0">
                                    <span
                                        class="bg-warning-transparent avatar avatar-md me-2 rounded-circle flex-shrink-0">
                                        <i class="ti ti-package fs-16"></i>
                                    </span>
                                    <div class="overflow-hidden">
                                        <h6 class="text-truncate mb-1">Exam Time Table Release</h6>
                                        <p><i class="ti ti-calendar me-2"></i>Added on : 24 May 2024</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark"><i class="ti ti-clck me-1"></i>06
                                    Days</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /Notice Board -->

            <!-- Fees Collection -->
            <div class="col-xxl-3 col-xl-6 order-2 order-xxl-3 d-flex flex-column">
                <div class="card flex-fill mb-2">
                    <div class="card-body">
                        <p class="mb-2">Total Fees Collected</p>
                        <div class="d-flex align-items-end justify-content-between">
                            <h4>$25,000,02</h4>
                            <span class="badge badge-soft-success"><i
                                    class="ti ti-chart-line me-1"></i>1.2%</span>
                        </div>
                    </div>
                </div>
                <div class="card flex-fill mb-2">
                    <div class="card-body">
                        <p class="mb-2">Fine Collected till date</p>
                        <div class="d-flex align-items-end justify-content-between">
                            <h4>$4,56,64</h4>
                            <span class="badge badge-soft-danger"><i
                                    class="ti ti-chart-line me-1"></i>1.2%</span>
                        </div>
                    </div>
                </div>
                <div class="card flex-fill mb-2">
                    <div class="card-body">
                        <p class="mb-2">Student Not Paid</p>
                        <div class="d-flex align-items-end justify-content-between">
                            <h4>$545</h4>
                            <span class="badge badge-soft-info"><i class="ti ti-chart-line me-1"></i>1.2%</span>
                        </div>
                    </div>
                </div>
                <div class="card flex-fill mb-4">
                    <div class="card-body">
                        <p class="mb-2">Total Outstanding</p>
                        <div class="d-flex align-items-end justify-content-between">
                            <h4>$4,56,64</h4>
                            <span class="badge badge-soft-danger"><i
                                    class="ti ti-chart-line me-1"></i>1.2%</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Fees Collection -->

        </div>

        <div class="row">

            <!-- Top Subjects -->
            <div class="col-xxl-4 col-xl-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Top Subjects</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-school-bell  me-2"></i>Class II
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class I
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class II
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class III
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Class IV
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success d-flex align-items-center mb-24" role="alert">
                            <i class="ti ti-info-square-rounded me-2 fs-14"></i>
                            <div class="fs-14">
                                These Result are obtained from the syllabus completion on the respective Class
                            </div>
                        </div>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Maths</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-primary rounded" role="progressbar"
                                                style="width: 20%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Physics</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-secondary rounded" role="progressbar"
                                                style="width: 30%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Chemistry</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-info rounded" role="progressbar"
                                                style="width: 40%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Botany</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-success rounded" role="progressbar"
                                                style="width: 50%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">English</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-warning rounded" role="progressbar"
                                                style="width: 70%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Spanish</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-danger rounded" role="progressbar"
                                                style="width: 80%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-sm-4">
                                        <p class="text-dark">Japanese</p>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="progress progress-xs flex-grow-1">
                                            <div class="progress-bar bg-primary rounded" role="progressbar"
                                                style="width: 85%;" aria-valuenow="30" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
            <!-- /Top Subjects -->

            <!-- Student Activity -->
            <div class="col-xxl-4 col-xl-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Student Activity</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-calendar me-2"></i>This Month
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Month
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Year
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center overflow-hidden p-3 mb-3 border rounded">
                            <span class="avatar avatar-lg flex-shrink-0 rounded me-2">
                                <img src="assets/img/students/student-09.jpg" alt="student">
                            </span>
                            <div class="overflow-hidden">
                                <h6 class="mb-1 text-truncate">1st place in "Chess”</h6>
                                <p>This event took place in Our School</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center overflow-hidden p-3 mb-3 border rounded">
                            <span class="avatar avatar-lg flex-shrink-0 rounded me-2">
                                <img src="assets/img/students/student-12.jpg" alt="student">
                            </span>
                            <div class="overflow-hidden">
                                <h6 class="mb-1 text-truncate">Participated in "Carrom"</h6>
                                <p>Justin Lee participated in "Carrom"</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center overflow-hidden p-3 mb-3 border rounded">
                            <span class="avatar avatar-lg flex-shrink-0 rounded me-2">
                                <img src="assets/img/students/student-11.jpg" alt="student">
                            </span>
                            <div class="overflow-hidden">
                                <h6 class="mb-1 text-truncate">1st place in "100M”</h6>
                                <p>This event took place in Our School</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center overflow-hidden p-3 mb-0 border rounded">
                            <span class="avatar avatar-lg flex-shrink-0 rounded me-2">
                                <img src="assets/img/students/student-10.jpg" alt="student">
                            </span>
                            <div class="overflow-hidden">
                                <h6 class="mb-1 text-truncate">International conference</h6>
                                <p class="text-truncate">We attended international conference</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /Student Activity -->

            <!-- Todo -->
            <div class="col-xxl-4 col-xl-12 d-flex">
                <div class="card flex-fill">
                    <div class="card-header  d-flex align-items-center justify-content-between">
                        <h4 class="card-title">Todo</h4>
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="bg-white dropdown-toggle"
                                data-bs-toggle="dropdown"><i class="ti ti-calendar me-2"></i>Today
                            </a>
                            <ul class="dropdown-menu mt-2 p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Month
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        This Year
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                        Last Week
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush todo-list">
                            <li class="list-group-item py-3 px-0 pt-0">
                                <div class="d-sm-flex align-items-center justify-content-between">
                                    <div
                                        class="d-flex align-items-center overflow-hidden me-2 todo-strike-content">
                                        <div class="form-check form-check-md me-2">
                                            <input class="form-check-input" type="checkbox" checked>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">Send Reminder to Students</h6>
                                            <p>01:00 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-success mt-2 mt-sm-0">Compeleted</span>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0">
                                <div class="d-sm-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <div class="form-check form-check-md me-2">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="mb-1 text-truncate">Create Routine to new staff</h6>
                                            <p>04:50 PM</p>
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-skyblue mt-2 mt-sm-0">Inprogress</span>
                                </div>
                            </li>
                            <li class="list-group-item py-3 px-0">
                                <div class="d-sm-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <div class="form-check form-check-md me-2">
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
                                <div class="d-sm-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <div class="form-check form-check-md me-2">
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
                                <div class="d-sm-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <div class="form-check form-check-md me-2">
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
                    </div>
                </div>
            </div>
            <!-- /Todo -->

        </div>
    </AuthenticatedLayout>
</template>
