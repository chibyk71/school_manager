<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Card } from 'primevue';
import { Head } from '@inertiajs/vue3';

    defineProps<{
        mailTemplates: {
            subject: string,
            model: string,
        }[]
    }>()
</script>

<template>
    <AuthenticatedLayout title="Email Template" :crumb="[{label:'Settings'},{label:'System'},{label:'Email Template'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <Head title="Email Templates" />
                <form action="email-templates.html">
                    <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                        <div class="mb-3">
                            <h5 class="mb-1">Email Templates</h5>
                            <p>Email Templates Configuration</p>
                        </div>
                        <div class="mb-3">
                            <Button class="" type="submit">Save</Button>
                        </div>
                    </div>
                    <Card class="card">
                        <template #content>
                            <div class="grid xxl:grid-cols-3 md:grid-cols-2 gap-4">
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border-2 rounded-lg mb-3">
                                        <h5 class="text-sm/none font-medium">Email Verification</h5>
                                        <div class="flex items-center gap-x-2">
                                            <Button icon="ti ti-edit" size="small" severity="secondary" variant="outlined" />
                                            <Button icon="ti ti-trash" size="small" severity="secondary" variant="outlined" />
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Welcome Email</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Feature Announcement</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Plan Announcement</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">User Remainder</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Feedback Request</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Plan Removal Remainder</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Plan Expired</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                        <h5 class="fs-15 fw-normal">Policy Update Notice</h5>
                                        <div class="flex items-center">
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon mr-2" data-bs-toggle="modal" data-bs-target="#edit_email_template"><i class="ti ti-edit"></i></a>
                                            <a href="email-templates.html#" class="btn btn-outline-light bg-white btn-icon" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template #content>
                    </Card>
                </form>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
