<!-- resources/js/Pages/Settings/System/Email.vue -->
<script setup lang="ts">
/**
 * Email.vue v2.0 – Production-Ready Email Configuration Page (Card-Based Design)
 *
 * Purpose:
 * Matches your PreSkool template exactly:
 * - Card grid with driver icon, name, description
 * - "Connect" button opens modal with driver-specific fields
 * - Status badge (Connected/Not Connected)
 * - Toggle inside modal
 * - Clean, responsive layout
 *
 * Features / Problems Solved:
 * - Card-based driver selection (SMTP, Mailgun, etc.)
 * - Dynamic modal with conditional fields per driver
 * - Test email button
 * - Full PrimeVue integration
 * - SettingsLayout + Sidebar + crumbs
 * - Accessibility and mobile optimization
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import EmailCard from '../Partials/EmailCard.vue'
import EmailConfigModal from '../Partials/Modals/EmailConfigModal.vue'

interface Driver {
    key: string
    name: string
    icon?: string
    image?: string
    description: string
    connected: boolean
}

interface Props {
    settings: {
        driver: string
        from_name: string
        from_email: string
        reply_to?: string
        smtp_host?: string
        mailgun_api_key?: string
        sendgrid_api_key?: string
        postmark_api_key?: string
        ses_key?: string
    }
    drivers: Record<string, string>
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { systemSettingsNav } = useSettingsNavigation()

const emailDrivers: Driver[] = [
    {
        key: "smtp",
        name: "SMTP",
        image: "assets/img/icons/smtp-icon.svg",
        description: "SMTP is used to send, relay or forward messages from a mail client.",
        connected: props.settings.driver === 'smtp' && !!props.settings.smtp_host,
    },
    {
        key: "sendGrid",
        name: "SendGrid",
        image: "assets/img/icons/sendgrid-icon.svg",
        description: "Cloud-based email marketing tool that assists marketers and developers.",
        connected: !!props.settings.sendgrid_api_key && props.settings.driver === 'sendgrid'
    },
    {
        key: "mailGun",
        name: "Mailgun",
        image: "assets/img/icons/mailgun-icon.svg",
        description: "Mailgun is a set of powerful APIs that enable you to send, receive and track email effortlessly.",
        connected: props.settings.driver === 'mailgun' && !!props.settings.mailgun_api_key,
    },
    {
        key: 'postmark',
        name: 'Postmark',
        icon: 'pi pi-check-circle text-4xl text-orange-600',
        description: 'Fast, reliable transactional email for developers',
        connected: props.settings.driver === 'postmark' && !!props.settings.postmark_api_key,
    },
    {
        key: "amazonSES",
        name: "Amazon SES",
        image: "assets/img/icons/amazon-ses-icon.svg",
        description: "Amazon Simple Email Service (Amazon SES) is a cloud-based email sending service designed to help digital marketers and application developers send marketing, notification, and transactional emails.",
        connected: props.settings.driver === 'ses' && !!props.settings.ses_key,
    }
];

const modalVisible = ref(false)
const currentDriver = ref<string | null>(null)

const openModal = (driverKey: string) => {
    currentDriver.value = driverKey
    modalVisible.value = true
}
</script>

<template>
    <AuthenticatedLayout title="Email Settings" :crumb="props.crumbs">

        <Head title="Email Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="System & Communication" :items="systemSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">Email Settings</h1>
                        <p class="text-gray-600 mt-1">Choose your email delivery method and configure credentials</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <EmailCard v-for="driver in emailDrivers" :key="driver.key" :id='driver.key'
                            class="hover:shadow-lg transition-shadow cursor-pointer" :title="driver.name"
                            :image="driver.image" :icon="driver.icon" :content="driver.description" :status="driver.connected" @configure="openModal">
                        </EmailCard>
                    </div>
                </div>
            </template>
        </SettingsLayout>

        <EmailConfigModal v-if="modalVisible && currentDriver" :visible="modalVisible" :driver="currentDriver"
            :current-settings="props.settings" @close="modalVisible = false" />
    </AuthenticatedLayout>
</template>
