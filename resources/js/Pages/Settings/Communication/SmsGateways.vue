<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head } from '@inertiajs/vue3'
import { Card, Badge } from 'primevue'
import { computed, ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import SmsProviderModal from '../Partials/Modals/SmsProviderModal.vue'

interface Provider {
    key: string
    name: string
    icon: string
    description: string
    enabled: boolean
}

interface Props {
    settings: { enabled: boolean; providers: Record<string, any> }
    providers: Record<string, any>
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { systemSettingsNav } = useSettingsNavigation()

const modalVisible = ref(false)
const currentProvider = ref<string | null>(null)

const providerList = computed<Provider[]>(() => {
    return Object.entries(props.providers).map(([key, meta]: [string, any]) => ({
        key,
        name: meta.name,
        icon: meta.icon,
        description: meta.description,
        enabled: props.settings.providers[key]?.enabled ?? false,
    }))
})

const openModal = (key: string) => {
    currentProvider.value = key
    modalVisible.value = true
}
</script>

<template>
    <AuthenticatedLayout title="SMS Gateways" :crumb="props.crumbs">

        <Head title="SMS Gateways" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="System & Communication" :items="systemSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">SMS Gateways</h1>
                        <p class="text-gray-600 mt-1">Configure SMS providers for notifications and alerts</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Card v-for="provider in providerList" :key="provider.key"
                            class="hover:shadow-lg transition-shadow">
                            <template #content>
                                <div class="flex flex-col h-full">
                                    <div class="flex items-center gap-4 mb-4">
                                        <i :class="provider.icon"></i>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg">{{ provider.name }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">{{ provider.description }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-auto flex items-center justify-between">
                                        <ToggleSwitch :modelValue="provider.enabled" disabled />
                                        <Button icon="pi pi-cog" severity="secondary" outlined
                                            @click="openModal(provider.key)" />
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </SettingsLayout>

        <SmsProviderModal v-if="modalVisible && currentProvider" :visible="modalVisible" :provider-key="currentProvider"
            :provider="props.providers[currentProvider]"
            :current-config="props.settings.providers[currentProvider] ?? {}" @close="modalVisible = false" />
    </AuthenticatedLayout>
</template>