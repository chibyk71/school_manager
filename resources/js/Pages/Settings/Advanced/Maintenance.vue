<!-- resources/js/Pages/Settings/Others/Maintenance.vue -->
<script setup lang="ts">
/**
 * Maintenance.vue v1.0 – Production-Ready Maintenance Mode Configuration Page
 *
 * Purpose:
 * Simple toggle to enable/disable maintenance mode with bypass key and optional custom page URL.
 *
 * Features / Problems Solved:
 * - Clean, focused layout with toggle and conditional fields
 * - Strong bypass key requirement (min 8 chars)
 * - Optional custom maintenance URL
 * - Responsive PrimeVue form
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputSwitch, InputText } from 'primevue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        mode: 'enabled' | 'disabled'
        bypass_key: string
        custom_url?: string
    }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { advancedSettingsNav } = useSettingsNavigation()

const form = useForm({
    mode: props.settings.mode ?? 'disabled',
    bypass_key: props.settings.bypass_key ?? '',
    custom_url: props.settings.custom_url ?? '',
})

const submit = () => {
    form.post(route('settings.others.maintenance.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Maintenance Mode" :crumb="props.crumbs">

        <Head title="Maintenance Mode" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Other Settings" :items="advancedSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Maintenance Mode</h1>
                                <p class="text-gray-600 mt-1">Temporarily disable public access for updates or
                                    emergencies</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <Card>
                            <template #content>
                                <div class="space-y-6">
                                    <div class="flex items-center gap-4">
                                        <InputSwitch v-model="form.mode" true-value="enabled" false-value="disabled" />
                                        <div>
                                            <label class="font-medium text-lg">
                                                {{ form.mode === 'enabled' ? 'Maintenance Mode Enabled' : 'Maintenance Mode Disabled' }}
                                            </label>
                                            <p class="text-sm text-gray-600">
                                                When enabled, only users with the bypass key can access the system.
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Bypass Key</label>
                                        <InputText v-model="form.bypass_key" type="password" fluid
                                            placeholder="Enter a strong key (min 8 characters)" />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Append ?key=yourkey to any URL to bypass maintenance mode.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Maintenance
                                            Page URL (optional)</label>
                                        <InputText v-model="form.custom_url" type="url" fluid
                                            placeholder="https://yourschool.com/maintenance" />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Users will be redirected here when maintenance is enabled.
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
