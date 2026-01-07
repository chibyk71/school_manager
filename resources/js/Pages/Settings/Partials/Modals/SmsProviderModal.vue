<script setup lang="ts">
import { Button, InputText, ToggleSwitch } from 'primevue'
import { useForm } from '@inertiajs/vue3'

interface Props {
    visible: boolean
    providerKey: string
    provider: any
    currentConfig: any
}

const props = defineProps<Props>()
const emit = defineEmits(['close'])

const form = useForm({
    enabled: props.currentConfig.enabled ?? false,
    priority: props.currentConfig.priority ?? 10,
    sender_id: props.currentConfig.sender_id ?? '',
    credentials: { ...props.currentConfig.credentials },
})

const fieldLabels: Record<string, string> = {
    api_key: 'API Key',
    api_secret: 'API Secret Key',
    account_sid: 'Account SID',
    auth_token: 'Auth Token',
}

const save = () => {
    form.post(route('settings.system.sms.store'), {
        data: {
            provider: props.providerKey,
            enabled: form.enabled,
            priority: form.priority,
            sender_id: form.sender_id,
            credentials: form.credentials,
        },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Dialog :visible="visible" :header="provider.name" modal @update:visible="emit('close')"
        :style="{ width: '500px' }">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <label class="font-medium">Status</label>
                <ToggleSwitch v-model="form.enabled" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID</label>
                <InputText v-model="form.sender_id" fluid placeholder="MySchool" />
            </div>

            <div v-for="field in provider.fields" :key="field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ fieldLabels[field] || field.replace('_', ' ').toUpperCase() }}
                </label>
                <InputText v-model="form.credentials[field]"
                    :type="field.includes('secret') || field.includes('token') ? 'password' : 'text'" fluid />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="emit('close')" />
            <Button label="Submit" @click="save" :loading="form.processing" />
        </template>
    </Dialog>
</template>