<!-- resources/js/Components/Modals/SocialProviderModal.vue -->
<script setup lang="ts">
/**
 * ! DO NOT DELETE OR RENAME THIS FILE !
 * SocialProviderModal.vue – Credential Input Modal for Social Providers
 *
 * Purpose:
 * Dedicated modal opened from Social.vue cards to input/edit OAuth credentials.
 * Emits 'saved' event with form data on success.
 * Includes copyable redirect URIs and provider-specific fields.
 */

import { Button, InputText, Textarea } from 'primevue'
import { ref } from 'vue'

const props = defineProps<{
    visible: boolean
    provider: {
        key: string
        name: string
        enabled: boolean
        client_id?: string
        client_secret?: string
        team_id?: string
        key_id?: string
        private_key?: string
        docs?: string
    }
    redirectUris: { login: string; register: string }
}>()

const emit = defineEmits(['close', 'saved'])

const form = ref({
    client_id: props.provider.client_id || '',
    client_secret: props.provider.client_secret || '',
    team_id: props.provider.team_id || '',
    key_id: props.provider.key_id || '',
    private_key: props.provider.private_key || '',
})

const copyUri = (type: 'login' | 'register') => {
    const uri = props.redirectUris[type].replace('{provider}', props.provider.key)
    navigator.clipboard.writeText(uri)
}

const save = () => {
    emit('saved', {
        client_id: form.value.client_id,
        client_secret: form.value.client_secret,
        team_id: form.value.team_id,
        key_id: form.value.key_id,
        private_key: form.value.private_key,
    })
}
</script>

<template>
    <Dialog :visible="visible" :header="`${provider.name} Integration`" modal :style="{ width: '500px' }"
        @update:visible="$emit('close')">
        <div class="space-y-6">
            <div v-if="provider.key !== 'apple'">
                <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                <InputText v-model="form.client_id" fluid />
            </div>

            <div v-if="provider.key !== 'apple'">
                <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                <InputText v-model="form.client_secret" type="password" fluid />
            </div>

            <!-- Apple-specific fields -->
            <template v-if="provider.key === 'apple'">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Services ID (Client ID)</label>
                    <InputText v-model="form.client_id" fluid />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Team ID</label>
                    <InputText v-model="form.team_id" fluid />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Key ID</label>
                    <InputText v-model="form.key_id" fluid />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Private Key (PEM)</label>
                    <Textarea v-model="form.private_key" rows="6" fluid />
                </div>
            </template>

            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm font-medium mb-3">Redirect URIs (copy to provider dashboard):</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <code class="text-xs bg-gray-200 px-2 py-1 rounded flex-1">{{ redirectUris.login.replace('{provider}',
                            provider.key) }}</code>
                        <Button size="small" label="Copy" @click="copyUri('login')" />
                    </div>
                    <div class="flex items-center gap-2">
                        <code class="text-xs bg-gray-200 px-2 py-1 rounded flex-1">{{ redirectUris.register.replace('{provider}',
                            provider.key) }}</code>
                        <Button size="small" label="Copy" @click="copyUri('register')" />
                    </div>
                </div>
                <a :href="provider.docs" target="_blank" class="text-xs text-primary-600 mt-3 inline-block">Setup Guide
                    →</a>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="$emit('close')" />
            <Button label="Save Credentials" @click="save" />
        </template>
    </Dialog>
</template>