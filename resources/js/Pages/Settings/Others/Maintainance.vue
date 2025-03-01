<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, InputText, Select } from 'primevue';
import { Head, useForm } from '@inertiajs/vue3';
import SettingsRow from '../Partials/SettingsRow.vue';

const form = useForm({
    maintainance_mode: 'Disabled',
    maintainance_key: null,
    maintainance_url: '/',
});

const submitted = () => {
    form.post(route('settings.maintainance'), {
        preserveScroll: true,
    });
};

</script>

<template>
    <Head title="Maintainance-Setting" />
    <AuthenticatedLayout title="Maintainance Settings" :crumb="[{label: 'Settings'},{label: 'Others'}, {label: 'Maintainance',url: '/settings/others/maintainance'}]">
        <SettingsLayout>
            <template #main>
                <form :action="route('settings.maintainance')" @submit.prevent="submitted" class="space-y-4 space-x-3">
                    <div class="flex items-center justify-between flex-wrap border-b px-3 pt-3">
                        <div class="mb-3 text-color">
                            <h5 class="mb-1">MainTainance Settings</h5>
                            <p>Collection of settings for MainTainance</p>
                        </div>
                        <div class="mb-3 space-x-3">
                            <Button type="submit" :loading="form.processing" label="Save"></Button>
                        </div>
                    </div>
                    <SettingsRow label="Maintainance Mode" description="Enable/Disable Maintainance Mode">
                        <Select class="w-full" :options="['Enable', 'Disabled']" v-model="form.maintainance_mode" fluid />
                    </SettingsRow>

                    <SettingsRow label="Maintainance Key" description="Key to bypass maintainance mode">
                        <InputText v-model="form.maintainance_key" fluid />
                    </SettingsRow>

                    <SettingsRow label="Maintainance URL" description="URL to Bypass maintainance mode">
                        <InputText v-model="form.maintainance_url" fluid />
                    </SettingsRow>
                </form>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
