<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Card, ToggleSwitch } from 'primevue';

defineProps<{
    settings: {}[]
}>()

</script>

<template>
    <AuthenticatedLayout title="SMS Setting" :crumb="[{label: 'Dashboard'},{label:'Settings'},{label:'System'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <Card>
                    <template #content class="card-body p-3 pb-0">
                        <div class="grid xxl:grid-cols-3 md:grid-cols-2 gap-4">
                            <div class="">
                                <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                    <span class="block"><img src="assets/img/icons/sms-icon-01.svg" alt="Img"></span>
                                    <div class="flex items-center gap-x-2.5">
                                        <ToggleSwitch />
                                        <Button v-tooltip.focus.top="'view integeation'" size="small" icon="ti ti-settings-cog" severity="secondary" variant="outlined" />
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                    <span class="block"><img src="assets/img/icons/sms-icon-02.svg" alt="Img"></span>
                                    <div class="flex items-center">
                                        <div class="status-toggle modal-status">
                                            <input type="checkbox" id="user2" class="check">
                                            <label for="user2" class="checktoggle"> </label>
                                        </div>
                                        <a href="sms-settings.html#" class="btn btn-outline-light bg-white btn-icon ml-2" data-bs-toggle="modal" data-bs-target="#connect_sms"><i class="ti ti-settings-cog"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="flex items-center justify-between bg-white p-3 border rounded mb-3">
                                    <span class="block"><img src="assets/img/icons/sms-icon-03.svg" alt="Img"></span>
                                    <div class="flex items-center">
                                        <div class="status-toggle modal-status">
                                            <input type="checkbox" id="user3" class="check">
                                            <label for="user3" class="checktoggle"> </label>
                                        </div>
                                        <a href="sms-settings.html#" class="btn btn-outline-light bg-white btn-icon ml-2" data-bs-toggle="modal" data-bs-target="#connect_sms"><i class="ti ti-settings-cog"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #header>
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3 mx-4">
                            <div class="mb-3">
                                <h5 class="mb-1">SMS Settings</h5>
                                <p>SMS Settings Configuration</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
