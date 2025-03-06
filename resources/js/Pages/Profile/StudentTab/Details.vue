<script setup lang="ts">
import { Avatar, Button, Card } from 'primevue';


</script>

<template>
    <div class="grid xxl:grid-cols-2 gap-4">
        <!-- Parents Information -->
        <Card class="card">
            <template #title class="card-header">
                <h5>Parents Information</h5>
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
        <Card>
            <template #title class="card-header">
                <h5>Documents</h5>
            </template>
            <template #content class="card-body">
                <div v-for="i in 2" class="bg-surface-100/50 border rounded flex items-center justify-between mb-3 p-2">
                    <div class="flex items-center overflow-hidden">
                        <Avatar icon="ti ti-pdf" />
                        <div class="ml-2">
                            <p class="line-clamp-1 font-medium text-dark">BirthCertificate.pdf</p>
                        </div>
                    </div>
                    <Button icon="ti ti-download" severity="contrast" size="small" />
                </div>
            </template>
        </Card>
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

        <!-- Previous School Details -->
        <div class="xxl:col-span-full">
            <Card class="card">
                <template #title>
                    <h5>Previous School Details</h5>
                </template>
                <template #content class="card-body pb-1">
                    <div class="grid md:grid-cols-2 gap-2">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="text-dark font-medium mb-1">Previous School Name</p>
                                <p>Oxford Matriculation, USA</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="text-dark font-medium mb-1">School Address</p>
                                <p>1852 Barnes Avenue, Cincinnati, OH 45202</p>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
        <!-- /Previous School Details -->


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
