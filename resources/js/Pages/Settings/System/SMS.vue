<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Card, ToggleSwitch } from 'primevue';

defineProps<{
    settings: {}[]
}>()

import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

// Props from controller
defineProps({
    settings: {
        type: Object,
        required: true
    },
    canManage: Boolean
})

// Form setup
const form = useForm({
    enabled: settings.enabled ?? true,
    global_sender_id: settings.global_sender_id ?? '',
    rate_limit_per_minute: settings.rate_limit_per_minute ?? 500,
    providers: { ...settings.providers } // Deep clone
})

// Submit
const submit = () => {
    form.post(route('settings.sms.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: show toast
        }
    })
}

// Provider logos (replace with real URLs or local assets)
const providerLogos = {
    nexmo: 'nexmo',
    twilio: 'twilio',
    africas_talking: 'africas-talking',
    multitexter: 'multitexter',
    bulk_sms_nigeria: 'bulk-sms-nigeria',
    beta_sms: 'beta-sms',
    gold_sms_247: 'gold-sms',
    smart_sms: 'smart-sms',
    x_wireless: 'x-wireless',
    kudi_sms: 'kudi-sms',
    mebo_sms: 'mebo-sms',
    nigerian_bulk_sms: 'nigerian-bulk-sms',
    ring_captcha: 'ring-captcha',
}

// Credential fields per provider
const credentialFields = {
    twilio: [
        { key: 'account_sid', label: 'Account SID', type: 'text' },
        { key: 'auth_token', label: 'Auth Token', type: 'password' }
    ],
    nexmo: [
        { key: 'api_key', label: 'API Key', type: 'text' },
        { key: 'api_secret', label: 'API Secret', type: 'password' }
    ],
    africas_talking: [
        { key: 'username', label: 'Username', type: 'text' },
        { key: 'api_key', label: 'API Key', type: 'password' }
    ],
    multitexter: [
        { key: 'username', label: 'Username / Email', type: 'text' },
        { key: 'password', label: 'Password', type: 'password' }
    ],
    bulk_sms_nigeria: [
        { key: 'token', label: 'API Token', type: 'password' }
    ],
    smart_sms: [
        { key: 'token', label: 'API Token', type: 'password' }
    ],
    beta_sms: [
        { key: 'username', label: 'Username', type: 'text' },
        { key: 'password', label: 'Password', type: 'password' }
    ],
    gold_sms_247: [
        { key: 'username', label: 'Username', type: 'text' },
        { key: 'password', label: 'Password', type: 'password' }
    ],
    x_wireless: [
        { key: 'client_id', label: 'Client ID', type: 'text' },
        { key: 'api_key', label: 'API Key', type: 'password' }
    ],
    // Add others as needed
    default: [
        { key: 'username', label: 'Username', type: 'text' },
        { key: 'password', label: 'Password', type: 'password' }
    ]
}

// Ordered providers for display
const orderedProviders = computed(() => {
    return Object.entries(form.providers)
        .sort(([, a], [, b]) => (a.priority || 999) - (b.priority || 999))
})

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
                                        <ToggleSwitch >
                                          <template #handle="slotProps"></template>
                                        </ToggleSwitch>
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


<template>
    <AppLayout title="SMS Settings">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">SMS Settings Configuration</h1>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Global Settings -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-medium mb-4">Global SMS Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMS Enabled</label>
                            <Switch v-model="form.enabled" :class="form.enabled ? 'bg-blue-600' : 'bg-gray-200'"
                                class="mt-2 relative inline-flex h-6 w-11 items-center rounded-full">
                                <span class="sr-only">Enable SMS</span>
                                <span :class="form.enabled ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                            </Switch>
                        </div>

                        <div>
                            <label for="global_sender_id" class="block text-sm font-medium text-gray-700">Default Sender
                                ID</label>
                            <input v-model="form.global_sender_id" type="text" maxlength="11"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p class="mt-1 text-xs text-gray-500">Fallback sender if provider-specific not set (max 11
                                chars)</p>
                        </div>

                        <div>
                            <label for="rate_limit" class="block text-sm font-medium text-gray-700">Rate Limit (per
                                minute)</label>
                            <input v-model.number="form.rate_limit_per_minute" type="number" min="10" max="5000"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <!-- Providers List -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium text-gray-900">SMS Providers (Drag to reorder priority)</h2>

                    <div v-for="[name, config] in orderedProviders" :key="name"
                        class="bg-white shadow rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <!-- Logo / Icon -->
                                <div
                                    class="w-12 h-12 bg-gray-200 border-2 border-dashed rounded-xl flex items-center justify-center">
                                    <span class="text-xs font-bold text-gray-600 uppercase">{{ name.slice(0, 2)
                                        }}</span>
                                </div>

                                <div>
                                    <h3 class="text-lg font-semibold capitalize">{{ name.replace(/_/g, ' ') }}</h3>
                                    <p class="text-sm text-gray-500">Priority: {{ config.priority }}</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-6">
                                <!-- Priority Input -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Priority</label>
                                    <input v-model.number="config.priority" type="number" min="1" max="999"
                                        class="mt-1 w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center" />
                                </div>

                                <!-- Enable Toggle -->
                                <Switch v-model="config.enabled"
                                    :class="config.enabled ? 'bg-green-600' : 'bg-gray-300'"
                                    class="relative inline-flex h-8 w-14 items-center rounded-full">
                                    <span class="sr-only">Enable {{ name }}</span>
                                    <span :class="config.enabled ? 'translate-x-8' : 'translate-x-1'"
                                        class="inline-block h-6 w-6 transform rounded-full bg-white transition" />
                                </Switch>
                            </div>
                        </div>

                        <!-- Sender ID -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Sender ID (optional)</label>
                            <input v-model="config.sender_id" type="text" maxlength="11" placeholder="e.g. MySchool"
                                class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>

                        <!-- Conditional Credentials -->
                        <transition name="fade">
                            <div v-if="config.enabled" class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="text-md font-medium mb-4">API Credentials</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-for="field in credentialFields[name] || credentialFields.default"
                                        :key="field.key">
                                        <label :for="field.key" class="block text-sm font-medium text-gray-700">{{
                                            field.label }}</label>
                                        <input :type="field.type" v-model="config.credentials[field.key]"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            :class="{ 'border-red-500': form.errors[`providers.${name}.credentials.${field.key}`] }" />
                                        <p v-if="form.errors[`providers.${name}.credentials.${field.key}`]"
                                            class="mt-1 text-sm text-red-600">
                                            {{ form.errors[`providers.${name}.credentials.${field.key}`] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4 pt-6">
                    <Link :href="route('dashboard')"
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
