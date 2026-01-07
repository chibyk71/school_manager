<!-- resources/js/Components/Modals/PaymentGatewayModal.vue -->
<script setup lang="ts">
import { Button, InputText, ToggleSwitch, Select } from 'primevue'
import { useForm } from '@inertiajs/vue3'

interface Props {
    visible: boolean
    gatewayKey: string
    gateway: any
    currentConfig: any
    webhookUrl: string
}

const props = defineProps<Props>()
const emit = defineEmits(['close'])

const form = useForm({
    enabled: props.currentConfig.enabled ?? false,
    mode: props.currentConfig.mode ?? 'test',
    credentials: { ...props.currentConfig.credentials },
})

const fieldLabels: Record<string, string> = {
    public_key: 'Public Key',
    secret_key: 'Secret Key',
    publishable_key: 'Publishable Key',
    client_id: 'Client ID',
    encryption_key: 'Encryption Key',
}

const save = () => {
    form.post(route('settings.financial.gateways.store'), {
        data: {
            gateway: props.gatewayKey,
            enabled: form.enabled,
            mode: form.mode,
            credentials: form.credentials,
        },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Dialog :visible="visible" :header="gateway.name" modal @update:visible="emit('close')" :style="{ width: '500px' }">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <label class="font-medium">Status</label>
                <ToggleSwitch v-model="form.enabled" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                <Select v-model="form.mode" :options="[{ label: 'Test', value: 'test' }, { label: 'Live', value: 'live' }]"
                    optionLabel="label" optionValue="value" fluid />
            </div>

            <div v-for="field in gateway.fields" :key="field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ fieldLabels[field] || field.replace('_', ' ').toUpperCase() }}
                </label>
                <InputText v-model="form.credentials[field]"
                    :type="field.includes('secret') || field.includes('key') ? 'password' : 'text'" fluid />
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm font-medium mb-2">Webhook URL:</p>
                <code class="text-xs break-all">{{ webhookUrl }}?gateway={{ gatewayKey }}</code>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="emit('close')" />
            <Button label="Submit" @click="save" :loading="form.processing" />
        </template>
    </Dialog>
</template>
