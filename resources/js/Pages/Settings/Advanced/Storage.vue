<!-- resources/js/Pages/Settings/Advanced/Storage.vue -->
<script setup lang="ts">
/**
 * Storage.vue v1.0 – Production-Ready File Storage Configuration Page
 *
 * Purpose:
 * Card-based driver selection (Local vs AWS S3) matching your PreSkool template exactly:
 * - Driver logo + name + description
 * - Toggle + gear icon (configure)
 * - Modal with AWS credentials + status
 *
 * Features / Problems Solved:
 * - Exact visual match to template
 * - Responsive grid
 * - Dynamic driver list
 * - Single reusable StorageModal.vue
 * - Clean main page
 * - Full PrimeVue integration
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head } from '@inertiajs/vue3'
import { Card, Badge, ToggleSwitch } from 'primevue'
import { computed, ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import StorageModal from '../Partials/Modals/StorageModal.vue'

interface Driver {
    key: string
    name: string
    logo: string
    description: string
    enabled: boolean
}

interface Props {
    settings: {
        [x: string]: any
        driver: string
        local?: { enabled: boolean }
        s3?: { enabled: boolean; config: any }
    }
    drivers: Record<string, any>
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { advancedSettingsNav } = useSettingsNavigation()

const modalVisible = ref(false)
const currentDriver = ref<string | null>(null)

const driverList = computed<Driver[]>(() => {
    return Object.entries(props.drivers).map(([key, meta]: [string, any]) => ({
        key,
        name: meta.name,
        logo: meta.logo,
        description: meta.description,
        enabled: props.settings[key]?.enabled ?? (props.settings.driver === key),
    }))
})

const openModal = (key: string) => {
    currentDriver.value = key
    modalVisible.value = true
}
</script>

<template>
    <AuthenticatedLayout title="Storage" :crumb="props.crumbs">

        <Head title="Storage Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Other Settings" :items="advancedSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">Storage</h1>
                        <p class="text-gray-600 mt-1">Configure where uploaded files (photos, documents, invoices) are
                            stored</p>
                        <p class="text-sm text-gray-500 mt-2">Current Driver: <strong>{{
                            props.settings.driver.toUpperCase() }}</strong></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Card v-for="driver in driverList" :key="driver.key" class="hover:shadow-lg transition-shadow">
                            <template #content>
                                <div class="flex flex-col h-full">
                                    <div class="flex items-center gap-4 mb-4">
                                        <img :src="`/assets/img/storage/${driver.logo}.svg`" alt="logo"
                                            class="h-12 w-12 object-contain" />
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg">{{ driver.name }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">{{ driver.description }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-auto flex items-center justify-between">
                                        <ToggleSwitch :modelValue="driver.enabled" disabled />
                                        <Button icon="pi pi-cog" severity="secondary" outlined
                                            @click="openModal(driver.key)" />
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </SettingsLayout>

        <StorageModal v-if="modalVisible && currentDriver" :visible="modalVisible" :driver-key="currentDriver"
            :driver="props.drivers[currentDriver]" :current-config="props.settings[currentDriver] ?? {}"
            @close="modalVisible = false" />
    </AuthenticatedLayout>
</template>