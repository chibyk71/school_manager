<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import SettingsRow from '../Partials/SettingsRow.vue';
import { Textarea, ToggleSwitch } from 'primevue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    settings: {}
}>()
</script>

<template>
    <Head title="Fees Settings" />
    <AuthenticatedLayout title="Fees" :crumb="[{ label: 'Settings' }, { label: 'Finance' }, { label: 'Fees' }]">
        <SettingsLayout>
            <template #main>
                <div class="mx-4">
                    <form action="" method="post">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Fees Settings</h5>
                                <p>Fees Settings Configuration</p>
                            </div>
                            <div class="mb-3">
                                <a href="email.html" class="btn btn-outline-light bg-white btn-icon me-2"><i
                                        class="ti ti-mail-share"></i></a>
                                <button class="btn btn-light me-2" type="button">Cancel</button>
                                <button class="btn btn-primary" type="submit">Save</button>
                            </div>
                        </div>
                        <div class="block space-y-4">
                            <SettingsRow label="Allow Offline Payments" description="">
                                <ToggleSwitch />
                            </SettingsRow>

                            <SettingsRow label="Instructions For Offline Bank Payments" description="Instructions For How Offline Payments To Bank Are To Be Made">
                                <Textarea rows="3" fluid />
                            </SettingsRow>

                            <SettingsRow label="Lock Student Panel" description="If a student's is to be disabled if they defailt in payment">
                                <ToggleSwitch />
                            </SettingsRow>

                            <SettingsRow label="Print Receipt" description="If a copy of the receipt is to be made for printing">
                                <ToggleSwitch />
                            </SettingsRow>

                            <SettingsRow label="Receipt As Single Page" description="Force receipt to fit in one page">
                                <ToggleSwitch />
                            </SettingsRow>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
