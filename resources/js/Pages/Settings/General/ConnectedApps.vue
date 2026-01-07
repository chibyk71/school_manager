<!-- resources/js/Pages/Settings/General/ConnectedApps.vue -->
<script setup lang="ts">
/**
 * ConnectedApps.vue v2.0 – Production-Ready Third-Party Integrations Dashboard
 *
 * Purpose:
 * Clean card-based dashboard matching your PreSkool template exactly:
 * - Service icon + name + description
 * - "Connect" button opens modal for configuration
 * - No status badge until connected (keeps it simple)
 * - Modal handles all credential input
 *
 * Features / Problems Solved:
 * - Exact visual match to your screenshot (Slack, Google Calendar, Gmail, GitHub cards)
 * - Responsive grid (1-3 columns)
 * - Dynamic service list from backend
 * - Single reusable IntegrationModal.vue for all services
 * - Clean main page – no credential clutter
 * - Full accessibility and PrimeVue integration
 * - SettingsLayout + Sidebar + crumbs
 *
 * Fits into the Settings Module:
 * - Navigation: General Settings → Connected Apps
 * - Controller: ConnectedAppsController
 * - Key: 'general.integrations'
 * - Modal: IntegrationModal.vue (dynamic fields per service)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import IntegrationModal from '../Partials/Modals/IntegrationModal.vue'
import { Head } from '@inertiajs/vue3'
import { Button, Card } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Service {
    name: string
    icon: string
    description: string
    fields: string[]
    docs: string
}

interface Props {
    integrations: Record<string, any>
    services: Record<string, Service>
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { generalSettingsNav } = useSettingsNavigation()

const modalVisible = ref(false)
const currentService = ref<string | null>(null)

const openModal = (serviceKey: string) => {
    currentService.value = serviceKey
    modalVisible.value = true
}
</script>

<template>
    <AuthenticatedLayout title="Connected Apps" :crumb="props.crumbs">

        <Head title="Connected Apps & Integrations" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="General Settings" :items="generalSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">Connected Apps</h1>
                        <p class="text-gray-600 mt-1">Connect third-party services to enhance your school management
                            experience</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Card v-for="(service, key) in props.services" :key="key"
                            class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal(key)">
                            <template #content>
                                <div class="flex flex-col h-full">
                                    <div class="flex items-start gap-4 mb-4">
                                        <i :class="service.icon"></i>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg">{{ service.name }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">{{ service.description }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-auto">
                                        <Button label="Connect" class="w-full" @click.stop="openModal(key)" />
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </SettingsLayout>

        <IntegrationModal v-if="modalVisible && currentService" :visible="modalVisible" :service-key="currentService"
            :service="props.services[currentService]" :current-config="props.integrations[currentService] ?? {}"
            @close="modalVisible = false" />
    </AuthenticatedLayout>
</template>