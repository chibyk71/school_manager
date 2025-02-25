<script setup lang="ts">
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { Badge, Button, Menu, ToggleSwitch } from 'primevue';
    import SettingsRow from '../Partials/SettingsRow.vue';

    const props = defineProps<{settings: {
        two_factor: boolean,
        is_email_verified: boolean,
        is_phone_verified: boolean
    }}>()



</script>

<template>
    <Head title="Profile Setting" />
    <AuthenticatedLayout title="Profile Settings" :crumb="[{label:'Settings'},{label:'Profile'}]" :buttons="[{icon: 'ti ti-refresh', severity: 'secondary'}]">
        <div class="grid grid-cols-12 gap-4 divide-x-2 border-t">
            <div class="xxl:col-span-2 xl:col-span-3 col-span-12">
                <!-- <Menu :model="[{label:'Profile Setting'}, {label: }]" /> -->
                <div class="pt-3 flex flex-col list-group mb-4 !text-color">
                    <a href="profile-settings.html" class="block rounded p-2">Profile Settings</a>
                    <a href="security-settings.html" class="block rounded active p-2">Security Settings</a>
                    <a href="notifications-settings.html" class="block rounded p-2">Notifications</a>
                    <a href="connected-apps.html" class="block rounded p-2">Connected Apps</a>
                </div>
            </div>
            <div class="xxl:col-span-10 xl:col-span-9 col-span-12">
                <div class="border-start ps-3 flex-fill">
                    <div
                        class="flex items-center justify-between flex-wrap border-b mb-3 pt-3">
                        <div class="mb-3">
                            <h5>Security Settings</h5>
                            <p>Upload your photo & personal details here</p>
                        </div>
                    </div>
                    <div class="block">
                        <SettingsRow label="Password" description="Set a unique password to protect the account">
                            <Button size="small" severity="primary" variant="outlined" label="Change Password" />
                        </SettingsRow>
                        <SettingsRow label="Two Factor" description="Receive codes via SMS or email every time you login">
                            <ToggleSwitch v-model="props.settings?.two_factor" />
                        </SettingsRow>
                        <SettingsRow label="Phone Number Verification" description="The Phone Number associated with the account">
                            <div class="flex items-center flex-wrap gap-x-2">
                                <Badge value="Verified" severity="success" />
                                <Button size="small" icon="ti ti-edit" label="Edit" variant="outlined" severity="contrast" />
                            </div>
                        </SettingsRow>
                        <SettingsRow label="Email Address" description="The email address associated with the account">
                            <div class="flex items-center flex-wrap gap-x-2">
                                <span>[email&#160;protected]</span>
                                <Badge severity="success" value="Verified" />
                                <Button size="small" icon="ti ti-edit" label="Edit" variant="outlined" severity="contrast" />
                            </div>
                        </SettingsRow>
                        <SettingsRow label="Device Management" description="The devices associated with the account">
                            <Button label="Manage" variant="outlined" size="small" />
                        </SettingsRow>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
