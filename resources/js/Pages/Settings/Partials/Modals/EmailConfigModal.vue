<!-- resources/js/Components/Modals/EmailConfigModal.vue -->
<script setup lang="ts">
/**
 * EmailConfigModal.vue – Dynamic Email Driver Configuration Modal
 *
 * Purpose:
 * Handles all driver-specific fields in a single reusable modal.
 * Includes test email button.
 */

import { Button, InputText, InputNumber, Select } from 'primevue'
import { router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

interface Props {
    visible: boolean
    driver: string
    currentSettings: any
}

const props = defineProps<Props>()
const emit = defineEmits(['close'])

const testEmail = ref('')

const form = useForm({
    driver: props.driver,
    from_name: props.currentSettings.from_name ?? '',
    from_email: props.currentSettings.from_email ?? '',
    reply_to: props.currentSettings.reply_to ?? '',
    // SMTP
    smtp_host: props.currentSettings.smtp_host ?? '',
    smtp_port: props.currentSettings.smtp_port ?? 587,
    smtp_encryption: props.currentSettings.smtp_encryption ?? 'tls',
    smtp_username: props.currentSettings.smtp_username ?? '',
    smtp_password: props.currentSettings.smtp_password ?? '',
    // API drivers
    mailgun_api_key: props.currentSettings.mailgun_api_key ?? '',
    sendgrid_api_key: props.currentSettings.sendgrid_api_key ?? '',
    postmark_api_key: props.currentSettings.postmark_api_key ?? '',
    ses_key: props.currentSettings.ses_key ?? '',
    ses_secret: props.currentSettings.ses_secret ?? '',
    ses_region: props.currentSettings.ses_region ?? 'us-east-1',
})

const apiKey = computed({
    get() {
        return (form as any)[`${props.driver}_api_key`]
    },
    set(value: any) {
        (form as any)[`${props.driver}_api_key`] = value
    },
})

const save = () => {
    form.post(route('settings.system.email.store'), {
        onSuccess: () => emit('close'),
    })
}

const sendTest = () => {
    // Call test endpoint
    router.post(route('settings.system.email.test'), { test_email: testEmail.value })
    testEmail.value = ''
}
</script>

<template>
    <Dialog :visible="visible" :header="`Configure ${driver.toUpperCase()}`" modal @update:visible="emit('close')"
        :style="{ width: '600px' }">
        <div class="space-y-6">
            <!-- Sender Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                    <InputText v-model="form.from_name" fluid />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                    <InputText v-model="form.from_email" type="email" fluid />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reply-To</label>
                    <InputText v-model="form.reply_to" type="email" fluid />
                </div>
            </div>

            <!-- SMTP Fields -->
            <template v-if="driver === 'smtp'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Host</label>
                        <InputText v-model="form.smtp_host" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Port</label>
                        <InputNumber v-model="form.smtp_port" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                        <Select v-model="form.smtp_encryption" :options="['tls', 'ssl', 'none']" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <InputText v-model="form.smtp_username" fluid />
                    </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <InputText v-model="apiKey" type="password" fluid />
                </div>
                </div>
            </template>

            <!-- API Key Fields -->
            <template v-else>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <InputText v-model="apiKey" type="password" fluid />
                </div>
                <!-- SES region -->
                <div v-if="driver === 'ses'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Region</label>
                    <Select v-model="form.ses_region" :options="['us-east-1', 'us-west-2', 'eu-west-1']" fluid />
                </div>
            </template>

            <!-- Test Email -->
            <div class="pt-6 border-t">
                <label class="block text-sm font-medium text-gray-700 mb-2">Send Test Email</label>
                <div class="flex gap-2">
                    <InputText v-model="testEmail" type="email" placeholder="test@example.com" fluid />
                    <Button label="Send Test" @click="sendTest" />
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="emit('close')" />
            <Button label="Save Configuration" @click="save" :loading="form.processing" />
        </template>
    </Dialog>
</template>
