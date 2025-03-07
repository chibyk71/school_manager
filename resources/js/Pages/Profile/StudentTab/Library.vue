<script lang="ts" setup>
import { Avatar, Card } from 'primevue';


</script>

<template>

    <Card>
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5>Library</h5>
            <div class="dropdown">
                <a href="javascript:void(0);"
                    class="btn btn-outline-light border-white bg-white dropdown-toggle shadow-md"
                    data-bs-toggle="dropdown"><i class="ti ti-calendar-due me-2"></i>This Year</a>
                <ul class="dropdown-menu p-3">
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item rounded-1">
                            This Year
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item rounded-1">
                            This Month
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item rounded-1">
                            This Week
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <template #content>
            <div class="grid xxl:grid-cols-3 md:grid-cols-2 gap-4">
                <!-- Book List -->
                <Card class="card mb-3" v-for="i in 4">
                    <template #content class="card-body pb-1">
                        <Avatar :image="`assets/img/books/book-0${i}.jpg`" size="xlarge" />
                        <h6 class="mb-3">The Small-Town Library</h6>
                        <div class="flex items-center gap-x-2">
                            <div class="w-1/2">
                                <div class="mb-3">
                                    <span class="text-xs">Book taken on </span>
                                    <p class="text-dark text-sm">25 Jan 2024</p>
                                </div>
                            </div>
                            <div class="w-1/2">
                                <div class="mb-3">
                                    <span class="text-xs/none">Last Date</span>
                                    <p class="text-dark text-sm">25 Jan 2024</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
                <!-- /Book List -->
            </div>
        </template>
    </Card>
</template>
