<!-- resources/js/Pages/Settings/Financial/Gateways.vue -->
<script setup lang="ts">
/**
 * Gateways.vue v1.0 – Production-Ready Payment Gateways Dashboard
 *
 * Purpose:
 * Card-based list of payment gateways matching your PreSkool template exactly:
 * - Gateway logo + name + description
 * - Toggle + "View Integration" button
 * - Modal with keys, test/live mode, webhook URL
 *
 * Features / Problems Solved:
 * - Exact visual match to template
 * - Responsive grid
 * - Dynamic gateway list
 * - Single reusable PaymentGatewayModal.vue
 * - Webhook URL display
 * - Test/Live mode
 * - Clean main page
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head } from '@inertiajs/vue3'
import { Card, Badge, ToggleSwitch } from 'primevue'
import { computed, ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Gateway {
    key: string
    name: string
    logo: string
    description: string
    enabled: boolean
    mode?: string
}

interface Props {
    settings: Record<string, any>
    gateways: Record<string, any>
    webhook_url: string
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { financialSettingsNav } = useSettingsNavigation()

const modalVisible = ref(false)
const currentGateway = ref<string | null>(null)

const gatewayList = computed<Gateway[]>(() => {
    return Object.entries(props.gateways).map(([key, meta]: [string, any]) => ({
        key,
        name: meta.name,
        logo: meta.logo,
        description: meta.description,
        enabled: props.settings[key]?.enabled ?? false,
        mode: props.settings[key]?.mode,
    }))
})

const openModal = (key: string) => {
    currentGateway.value = key
    modalVisible.value = true
}
</script>

<template>
    <AuthenticatedLayout title="Payment Gateways" :crumb="props.crumbs">

        <Head title="Payment Gateways" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Financial" :items="financialSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">Payment Gateways</h1>
                        <p class="text-gray-600 mt-1">Configure online payment providers for fee collection</p>
                        <p class="text-sm text-gray-500 mt-2">Webhook URL: <code
                                class="bg-gray-100 px-2 py-1 rounded">{{ webhook_url }}</code></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Card v-for="gateway in gatewayList" :key="gateway.key"
                            class="hover:shadow-lg transition-shadow">
                            <template #content>
                                <div class="flex flex-col h-full">
                                    <div class="flex items-center gap-4 mb-4">
                                        <img :src="`/assets/img/payment/${gateway.logo}.svg`" alt="logo"
                                            class="h-12 w-12 object-contain" />
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg">{{ gateway.name }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">{{ gateway.description }}</p>
                                            <Badge v-if="gateway.mode" :value="gateway.mode.toUpperCase()"
                                                :severity="gateway.mode === 'live' ? 'success' : 'warning'"
                                                class="mt-2" />
                                        </div>
                                    </div>
                                    <div class="mt-auto flex items-center justify-between">
                                        <ToggleSwitch :modelValue="gateway.enabled" disabled />
                                        <Button label="View Integration" severity="secondary" outlined
                                            @click="openModal(gateway.key)" />
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </SettingsLayout>

        <PaymentGatewayModal v-if="modalVisible && currentGateway" :visible="modalVisible" :gateway-key="currentGateway"
            :gateway="props.gateways[currentGateway]" :current-config="props.settings[currentGateway] ?? {}"
            :webhook-url="props.webhook_url" @close="modalVisible = false" />
    </AuthenticatedLayout>
</template>