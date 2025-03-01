<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Avatar, Button, ToggleSwitch } from 'primevue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({

})

</script>

<template>
    <Head title="Storage Setting" />
    <AuthenticatedLayout title="Storage Settings" :crumb="[{label:'Settings'},{label:'Others'},{label:'Storage'}]">
        <SettingsLayout>
            <template #main>
                <form action="" method="post" class="">
                    <div class="flex items-center justify-between flex-wrap border-b px-3 pt-3">
                        <div class="mb-3 text-color">
                            <h5 class="mb-1">Storage Settings</h5>
                            <p>Collection of settings for MainTainance</p>
                        </div>
                        <div class="mb-3 space-x-3">
                            <Button type="submit" :loading="form.processing" label="Save"></Button>
                        </div>
                    </div>
                    <div class="grid xxl:grid-cols-3 md:grid-cols-2 gap-4 mx-4 pt-4">
                        <div class="flex items-center justify-between bg-surface-50 dark:bg-dark p-3 border rounded mb-3">
                            <div class="flex items-center">
                                <Avatar image="assets/img/icons/storage-icon-01.svg" shape="circle" size="normal"  />
                                <h6 class="ml-2 dark:text-surface-50">Local Storage</h6>
                            </div>
                            <div class="flex items-center">
                                <ToggleSwitch />
                                <Button icon="ti ti-settings-cog" severity="secondary" variant="outlined" class="ml-2"/>
                            </div>
                        </div>

                        <div class="flex items-center justify-between bg-surface-50 dark:bg-dark p-3 border rounded mb-3">
                            <div class="flex items-center">
                                <Avatar image="assets/img/icons/storage-icon-02.svg" shape="circle" size="normal"  />
                                <h6 class="ml-2 dark:text-surface-50">AWS</h6>
                            </div>
                            <div class="flex items-center">
                                <ToggleSwitch />
                                <Button icon="ti ti-settings-cog" severity="secondary" variant="outlined" class="ml-2"/>
                            </div>
                        </div>
                    </div>
                </form>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
