<!-- resources/js/Components/Modals/StorageModal.vue -->
<script setup lang="ts">
import { Button, InputText, ToggleSwitch } from 'primevue'
import { useForm } from '@inertiajs/vue3'

interface Props {
    visible: boolean
    driverKey: string
    driver: any
    currentConfig: any
}

const props = defineProps<Props>()
const emit = defineEmits(['close'])

const form = useForm({
    enabled: props.currentConfig.enabled ?? false,
    config: { ...props.currentConfig.config },
})

const fieldLabels: Record<string, string> = {
    key: 'Access Key',
    secret: 'Secret Key',
    bucket: 'Bucket Name',
    region: 'Region',
    url: 'Base URL',
}

const save = () => {
    form.post(route('settings.advanced.storage.store'), {
        data: {
            driver: props.driverKey,
            enabled: form.enabled,
            config: form.config,
        },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Dialog :visible="visible" :header="driver.name + ' Settings'" modal @update:visible="emit('close')"
        :style="{ width: '500px' }">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <label class="font-medium">Status</label>
                <ToggleSwitch v-model="form.enabled" />
            </div>

            <div v-for="field in driver.fields" :key="field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ fieldLabels[field] || field.replace('_', ' ').toUpperCase() }}
                </label>
                <InputText v-model="form.config[field]" :type="field.includes('secret') ? 'password' : 'text'" fluid />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="emit('close')" />
            <Button label="Submit" @click="save" :loading="form.processing" />
        </template>
    </Dialog>
</template>