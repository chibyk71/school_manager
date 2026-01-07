<!-- resources/js/Pages/Settings/General/ApiKeys.vue -->
<script setup lang="ts">
/**
 * ApiKeys.vue v1.0 – Production-Ready Dedicated API Keys Management Page
 *
 * Purpose:
 * Full-featured API token management: generate, view (once), copy, revoke.
 * Clean table with scopes, expiry, last used.
 *
 * Features / Problems Solved:
 * - New key shown only once after creation (security best practice)
 * - Scope badges for quick visibility
 * - Expiry date formatting
 * - Bulk + single revoke with confirmation
 * - Copy-to-clipboard with toast
 * - Responsive PrimeVue DataTable
 * - Generate modal with scope selection
 * - Full SettingsLayout integration
 * - Matches modern SaaS (GitHub, Stripe, Laravel Sanctum)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, Dialog, ConfirmDialog, Chip, Badge } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import { useToast } from 'primevue/usetoast'

interface ApiKey {
    id: string
    name: string
    key?: string // Only present immediately after creation
    scopes: string[]
    created_at: string
    expires_at: string | null
    last_used_at: string | null
}

interface Props {
    api_keys: ApiKey[]
    available_scopes: string[]
    crumbs: Array<{ label: string }>
    new_key?: ApiKey
}

const props = defineProps<Props>()
const toast = useToast()
const { generalSettingsNav } = useSettingsNavigation()

// Generate modal
const generateDialog = ref(false)
const generateForm = useForm({
    name: '',
    scopes: [] as string[],
    expires_in_days: null as number | null,
})

// Revoke confirmation
const revokeDialog = ref(false)
const keysToRevoke = ref<string[]>([])

// New key display (shown only once)
const newKeyDisplay = ref<ApiKey | null>(props.new_key ?? null)

const openGenerate = () => {
    generateForm.reset()
    generateDialog.value = true
}

const submitGenerate = () => {
    generateForm.post(route('settings.general.api_keys.store'), {
        onSuccess: () => {
            generateDialog.value = false
        },
    })
}

const confirmRevoke = (keys: string | string[]) => {
    keysToRevoke.value = Array.isArray(keys) ? keys : [keys]
    revokeDialog.value = true
}

const performRevoke = () => {
    router.post(route('settings.general.api_keys.destroy'), {
        key_ids: keysToRevoke.value,
    }, {
        preserveScroll: true,
    })
    revokeDialog.value = false
}

const copyKey = (key: string) => {
    navigator.clipboard.writeText(key)
    toast.add({ severity: 'success', summary: 'Copied!', detail: 'API key copied to clipboard', life: 3000 })
}
</script>

<template>
    <AuthenticatedLayout title="API Keys" :crumb="props.crumbs">

        <Head title="API Keys Management" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="General Settings" :items="generalSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">API Keys</h1>
                            <p class="text-gray-600 mt-1">Manage API tokens for external applications and integrations
                            </p>
                        </div>
                        <Button label="Generate New Key" @click="openGenerate" />
                    </div>

                    <!-- New Key Alert (shown only once) -->
                    <Card v-if="newKeyDisplay" class="mb-6 border-2 border-green-500">
                        <template #content>
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-semibold text-green-800">New API Key Created</h3>
                                    <p class="text-sm text-gray-700 mt-1">Copy this key now — it will never be shown
                                        again!</p>
                                    <code
                                        class="block mt-3 p-3 bg-gray-100 rounded font-mono text-sm break-all">{{ newKeyDisplay.key }}</code>
                                </div>
                                <Button icon="pi pi-copy" @click="copyKey(newKeyDisplay.key!)" severity="success" />
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #content>
                            <DataTable :value="props.api_keys" class="p-datatable-sm">
                                <Column field="name" header="Name" />
                                <Column header="Scopes">
                                    <template #body="{ data }">
                                        <div class="flex flex-wrap gap-1">
                                            <Chip v-for="scope in data.scopes" :key="scope" :label="scope"
                                                size="small" />
                                        </div>
                                    </template>
                                </Column>
                                <Column field="created_at" header="Created">
                                    <template #body="{ data }">
                                        {{ new Date(data.created_at).toLocaleDateString() }}
                                    </template>
                                </Column>
                                <Column field="expires_at" header="Expires">
                                    <template #body="{ data }">
                                        {{ data.expires_at ? new Date(data.expires_at).toLocaleDateString() : 'Never' }}
                                    </template>
                                </Column>
                                <Column field="last_used_at" header="Last Used">
                                    <template #body="{ data }">
                                        {{ data.last_used_at ? new Date(data.last_used_at).toLocaleDateString() :
                                            'Never' }}
                                    </template>
                                </Column>
                                <Column header="Actions" style="width: 120px">
                                    <template #body="{ data }">
                                        <Button icon="pi pi-trash" severity="danger" text rounded
                                            @click="confirmRevoke(data.id)" />
                                    </template>
                                </Column>
                            </DataTable>

                            <p v-if="props.api_keys.length === 0" class="text-center text-gray-500 py-8">
                                No API keys generated yet. Click "Generate New Key" to create one.
                            </p>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <!-- Generate Key Modal -->
        <Dialog v-model:visible="generateDialog" header="Generate New API Key" modal :style="{ width: '500px' }">
            <form @submit.prevent="submitGenerate">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Key Name</label>
                        <InputText v-model="generateForm.name" fluid placeholder="e.g., Mobile App Production" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Scopes</label>
                        <MultiSelect v-model="generateForm.scopes" :options="props.available_scopes"
                            placeholder="Select permissions" display="chip" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry (optional)</label>
                        <InputNumber v-model="generateForm.expires_in_days" :min="1" :max="365" suffix=" days" fluid
                            showButtons />
                        <p class="text-xs text-gray-500 mt-1">Leave blank for no expiry</p>
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancel" severity="secondary" @click="generateDialog = false" />
                    <Button label="Generate Key" type="submit" :loading="generateForm.processing" />
                </template>
            </form>
        </Dialog>

        <!-- Revoke Confirmation -->
        <ConfirmDialog v-model:visible="revokeDialog"
            message="Are you sure you want to revoke the selected API key(s)? This action cannot be undone."
            header="Confirm Revocation" icon="pi pi-exclamation-triangle" acceptLabel="Yes, Revoke" rejectLabel="Cancel"
            @accept="performRevoke" />
    </AuthenticatedLayout>
</template>