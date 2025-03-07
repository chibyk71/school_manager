<script setup lang="ts">
import { Avatar, Button, Card } from 'primevue';
import DocumentsCard from '../Partials/documentsCard.vue';


</script>

<template>
    <div class="grid xxl:grid-cols-2 gap-4">
        <!-- Parents Information -->
        <Card class="card">
            <template #title class="card-header">
                <h5>Sponsors/Garantors Information</h5>
            </template>
            <template #content class="card-body">
                <div class="border rounded p-3 pb-0 mb-3">
                    <div class="grid grid-cols-12">
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center mb-3">
                                <Avatar image="assets/img/parents/parent-13.jpg" size="large" />
                                <div class="ml-2 overflow-hidden">
                                    <h6 class="line-clamp-1">Jerald Vicinius</h6>
                                    <p class="text-primary">Father</p>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="mb-3">
                                <p class="text-dark font-medium">Phone</p>
                                <p>+1 45545 46464</p>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center justify-between">
                                <div class="overflow-hidden mr-3">
                                    <p class="text-dark font-medium">Email</p>
                                    <p class="line-clamp-1"><a
                                            href="https://preskool.dreamstechnologies.com/cdn-cgi/l/email-protection"
                                            class="__cf_email__"
                                            data-cfemail="167c73647756736e777b667a733875797b">[email&#160;protected]</a>
                                    </p>
                                </div>
                                <Button icon="ti ti-lock-x" severity="contrast" v-tooltip="'Reset Password'"
                                    size="small" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border rounded p-3 pb-0 mb-3">
                    <div class="grid grid-cols-12">
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center mb-3">
                                <Avatar image="assets/img/parents/parent-14.jpg" size="large" />
                                <div class="ml-2 overflow-hidden">
                                    <h6 class="line-clamp-1">Jerald Vicinius</h6>
                                    <p class="text-primary">Mother</p>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="mb-3">
                                <p class="text-dark font-medium">Phone</p>
                                <p>+1 45545 46464</p>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center justify-between">
                                <div class="overflow-hidden mr-3">
                                    <p class="text-dark font-medium">Email</p>
                                    <p class="line-clamp-1"><a
                                            href="https://preskool.dreamstechnologies.com/cdn-cgi/l/email-protection"
                                            class="__cf_email__"
                                            data-cfemail="167c73647756736e777b667a733875797b">[email&#160;protected]</a>
                                    </p>
                                </div>
                                <Button icon="ti ti-lock-x" severity="contrast" v-tooltip="'Reset Password'"
                                    size="small" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border rounded p-3 pb-0 mb-3">
                    <div class="grid grid-cols-12">
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center mb-3">
                                <Avatar image="assets/img/parents/parent-13.jpg" size="large" />
                                <div class="ml-2 overflow-hidden">
                                    <h6 class="line-clamp-1">Jerald Vicinius</h6>
                                    <p class="text-primary">Gaurdian (Father)</p>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="mb-3">
                                <p class="text-dark font-medium">Phone</p>
                                <p>+1 45545 46464</p>
                            </div>
                        </div>
                        <div class="sm:col-span-6 lg:col-span-4">
                            <div class="flex items-center justify-between">
                                <div class="overflow-hidden mr-3">
                                    <p class="text-dark font-medium">Email</p>
                                    <p class="line-clamp-1"><a
                                            href="https://preskool.dreamstechnologies.com/cdn-cgi/l/email-protection"
                                            class="__cf_email__"
                                            data-cfemail="167c73647756736e777b667a733875797b">[email&#160;protected]</a>
                                    </p>
                                </div>
                                <Button icon="ti ti-lock-x" severity="contrast" v-tooltip="'Reset Password'"
                                    size="small" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Parents Information -->

        <!-- Documents -->
        <DocumentsCard />
        <!-- /Documents -->

        <!-- Address -->
        <Card class="card flex-fill">
            <template #title class="card-header">
                <h5>Address</h5>
            </template>
            <template #content>
                <div v-for="i in 2" class="flex gap-x-2 items-center mb-3">
                    <Avatar icon="ti ti-map-pin-up" />
                    <div>
                        <p class="text-dark font-medium">Current Address</p>
                        <p>3495 Red Hawk Road, Buffalo Lake, MN 55314</p>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Address -->


        <!-- Medical History -->
        <Card class="card flex-fill">
            <template #title class="card-header">
                <h5>Medical History</h5>
            </template>
            <template #content>
                <div class="grid md:grid-cols-2">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <p class="text-dark font-medium">Known Allergies</p>
                            <Badge severity="secondary" class="text-dark">Rashes</Badge>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <p class="text-dark font-medium">Medications</p>
                            <p>-</p>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Medical History -->

        <!-- Bank Details -->
        <Card>
            <template #title class="card-header">
                <h5>Bank Details</h5>
            </template>
            <template #content class="card-body pb-1">
                <div class="grid grid-cols-3">
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Bank Name</p>
                        <p>Bank of America</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Branch</p>
                        <p>Cincinnati</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">IFSC</p>
                        <p>BOA83209832</p>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Bank Details -->

        <!-- Employment Details -->
        <Card class="divide-y">
            <template #title>
                <h5>Employment Details</h5>
            </template>
            <template #content>
                <div class="grid grid-cols-3">
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Contract Type</p>
                        <p>Permanent</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Shift</p>
                        <p>Morning</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Work Location</p>
                        <p>2nd Floor</p>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Employment Details -->

        <!-- Medical History -->
        <Card>
            <template #title class="card-header">
                <h5>Social Media</h5>
            </template>
            <template #content class="card-body pb-1">
                <div class="grid xxl:grid-cols-5 xl:grid-cols-3 gap-2">

                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Facebook</p>
                        <p>www.facebook.com</p>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Twitter</p>
                        <p>www.twitter.com</p>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Linkedin</p>
                        <p>www.linkedin.com</p>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Youtube</p>
                        <p>www.youtube.com</p>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1 text-dark font-medium">Instagram</p>
                        <p>www.instagram.com</p>
                    </div>
                </div>
            </template>
        </Card>
        <!-- /Medical History -->

        <!-- Other Info -->
        <div class="xxl:col-span-full">
            <Card>
                <template #title class="card-header">
                    <h5>Other Info</h5>
                </template>
                <template #content class="card-body">
                    <p>Depending on the specific needs of your organization or system, additional information may be
                        collected or tracked. It's important to ensure that any data collected complies with privacy
                        regulations and policies to protect students' sensitive information.</p>
                </template>
            </Card>
        </div>
        <!-- /Other Info -->
    </div>
</template>
