<!-- resources/js/Components/Modals/IntegrationModal.vue -->
<script setup lang="ts">
/**
 * IntegrationModal.vue – Dynamic Configuration Modal for All Services
 *
 * Purpose:
 * Single reusable modal that renders different fields based on the service.
 * Handles all credential input and save logic.
 */

import { Button, InputText, ToggleSwitch } from 'primevue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

interface Props {
    visible: boolean
    serviceKey: string
    service: {
        name: string
        fields: string[]
        docs: string
    }
    currentConfig: {
        enabled: boolean
        config: Record<string, string>
    }
}

const props = defineProps<Props>()
const emit = defineEmits(['close'])

const form = useForm({
    enabled: props.currentConfig.enabled ?? false,
    config: { ...props.currentConfig.config },
})

const fieldLabels: Record<string, string> = {
    bot_token: 'Bot Token',
    signing_secret: 'Signing Secret',
    client_id: 'Client ID',
    client_secret: 'Client Secret',
    redirect_uri: 'Redirect URI',
    personal_access_token: 'Personal Access Token',
    phone_number_id: 'Phone Number ID',
    access_token: 'Access Token',
    api_key: 'API Key',
    api_secret: 'API Secret',
}

const save = () => {
    form.post(route('settings.general.connected_apps.store'), {
        data: {
            service: props.serviceKey,
            enabled: form.enabled,
            config: form.config,
        },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Dialog :visible="visible" :header="`Connect ${service.name}`" modal @update:visible="emit('close')"
        :style="{ width: '500px' }">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <ToggleSwitch v-model="form.enabled" />
                <label class="font-medium">Enable this integration</label>
            </div>

            <div v-for="field in service.fields" :key="field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ fieldLabels[field] || field.replace('_', ' ').toUpperCase() }}
                </label>
                <InputText v-model="form.config[field]"
                    :type="field.includes('secret') || field.includes('token') ? 'password' : 'text'" fluid />
            </div>

            <div class="text-sm text-gray-500">
                <a :href="service.docs" target="_blank" class="text-primary-600 hover:underline">
                    View documentation →
                </a>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="emit('close')" />
            <Button label="Save & Connect" @click="save" :loading="form.processing" />
        </template>
    </Dialog>
</template>
