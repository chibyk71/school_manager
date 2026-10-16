<!-- File Manager Phase 1 — Storage provider management -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, Dialog, InputText, Select, ToggleSwitch, Message, Tag, Checkbox } from 'primevue'
import { computed, ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import axios from 'axios'

interface Provider {
    id: string
    name: string
    driver: string
    driver_label: string
    ownership: string
    school_id: string | null
    config: Record<string, unknown>
    secrets_configured: boolean
    enabled: boolean
    capabilities?: Record<string, unknown>
}

interface Props {
    providers: Provider[]
    canManage: boolean
    activeSchoolId: string | null
    defaultDriver?: string
    schoolProviderManagementAllowed?: boolean
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { advancedSettingsNav } = useSettingsNavigation()

const dialogVisible = ref(false)
const editing = ref<Provider | null>(null)
const testResult = ref<{ success: boolean; message: string } | null>(null)
const testLoading = ref(false)
const saving = ref(false)

const form = useForm({
    name: '',
    driver: 'local' as 'local' | 's3',
    ownership: 'school' as 'system' | 'school',
    config: {
        root: '',
        key: '',
        region: '',
        bucket: '',
        url: '',
        endpoint: '',
        use_path_style_endpoint: false,
    } as Record<string, unknown>,
    secrets: {
        secret: '',
    } as Record<string, string>,
    clear_secrets: false,
    enabled: true,
})

const isS3 = computed(() => form.driver === 's3')
const ownershipLocked = computed(() => editing.value !== null)

const openCreate = () => {
    editing.value = null
    testResult.value = null
    form.reset()
    form.driver = (props.defaultDriver === 's3' ? 's3' : 'local')
    form.ownership = props.activeSchoolId ? 'school' : 'system'
    form.enabled = true
    form.clear_secrets = false
    dialogVisible.value = true
}

const openEdit = (p: Provider) => {
    editing.value = p
    testResult.value = null
    form.name = p.name
    form.driver = p.driver as 'local' | 's3'
    form.ownership = p.ownership as 'system' | 'school'
    form.enabled = p.enabled
    form.clear_secrets = false
    form.config = {
        root: (p.config?.root as string) ?? '',
        key: (p.config?.key as string) ?? '',
        region: (p.config?.region as string) ?? '',
        bucket: (p.config?.bucket as string) ?? '',
        url: (p.config?.url as string) ?? '',
        endpoint: (p.config?.endpoint as string) ?? '',
        use_path_style_endpoint: Boolean(p.config?.use_path_style_endpoint),
    }
    form.secrets = { secret: '' }
    dialogVisible.value = true
}

const payload = () => {
    const data: Record<string, unknown> = {
        name: form.name,
        enabled: form.enabled,
        config: { ...form.config },
    }
    if (!editing.value) {
        data.driver = form.driver
        data.ownership = form.ownership
    }
    if (form.secrets.secret) {
        data.secrets = { secret: form.secrets.secret }
    }
    if (form.clear_secrets) {
        data.clear_secrets = true
    }
    return data
}

const save = async () => {
    if (!props.canManage) return
    saving.value = true
    testResult.value = null
    try {
        if (editing.value) {
            await axios.patch(route('settings.storage-providers.update', editing.value.id), payload())
        } else {
            await axios.post(route('settings.storage-providers.store'), {
                ...payload(),
                driver: form.driver,
                ownership: form.ownership,
            })
        }
        dialogVisible.value = false
        router.reload({ only: ['providers'] })
    } catch (e: any) {
        const msg = e?.response?.data?.message
            || e?.response?.data?.errors
            || 'Save failed.'
        testResult.value = { success: false, message: typeof msg === 'string' ? msg : JSON.stringify(msg) }
    } finally {
        saving.value = false
    }
}

const runTest = async () => {
    testLoading.value = true
    testResult.value = null
    try {
        const body: Record<string, unknown> = {
            driver: form.driver,
            ownership: form.ownership,
            config: { ...form.config },
            secrets: form.secrets.secret ? { secret: form.secrets.secret } : {},
        }
        const { data } = await axios.post(route('settings.storage-providers.test-connection'), body)
        testResult.value = { success: Boolean(data.success), message: data.message ?? '' }
    } catch (e: any) {
        const msg = e?.response?.data?.message || 'Connection test failed.'
        testResult.value = { success: false, message: msg }
    } finally {
        testLoading.value = false
    }
}

const ownershipLabel = (o: string) => (o === 'system' ? 'System' : 'School')
</script>

<template>
    <AuthenticatedLayout title="Storage Providers" :crumb="props.crumbs">
        <Head title="Storage Providers" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Other Settings" :items="advancedSettingsNav" />
            </template>

            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-xl font-semibold">Storage Providers</h2>
                        <p class="text-sm text-surface-500 mt-1">
                            Configure local and S3-compatible storage destinations.
                            Secrets are encrypted and never displayed after save.
                            Legacy Advanced Storage settings remain available separately.
                        </p>
                    </div>
                    <Button
                        v-if="canManage"
                        label="Add provider"
                        icon="pi pi-plus"
                        @click="openCreate"
                    />
                </div>

                <div v-if="!providers.length" class="text-surface-500 text-sm py-8 text-center border border-dashed rounded-lg">
                    No storage providers configured in this scope yet.
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <Card v-for="p in providers" :key="p.id" class="shadow-sm">
                        <template #title>
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ p.name }}</span>
                                <Tag :value="ownershipLabel(p.ownership)" :severity="p.ownership === 'system' ? 'info' : 'secondary'" />
                            </div>
                        </template>
                        <template #subtitle>
                            {{ p.driver_label }}
                            <span v-if="p.secrets_configured" class="ml-2 text-xs text-green-600">· secrets configured</span>
                            <span v-else class="ml-2 text-xs text-surface-400">· no secrets</span>
                        </template>
                        <template #content>
                            <div class="flex items-center gap-2 text-sm">
                                <Tag :value="p.enabled ? 'Enabled' : 'Disabled'" :severity="p.enabled ? 'success' : 'warn'" />
                            </div>
                        </template>
                        <template #footer>
                            <Button
                                v-if="canManage"
                                label="Edit"
                                size="small"
                                text
                                @click="openEdit(p)"
                            />
                        </template>
                    </Card>
                </div>
            </div>
        </SettingsLayout>

        <Dialog
            v-model:visible="dialogVisible"
            :header="editing ? 'Edit storage provider' : 'New storage provider'"
            modal
            class="w-full max-w-lg"
        >
            <div class="flex flex-col gap-3">
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <InputText v-model="form.name" class="w-full mt-1" />
                </div>

                <div v-if="!ownershipLocked" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Driver</label>
                        <Select
                            v-model="form.driver"
                            :options="[
                                { label: 'Local filesystem', value: 'local' },
                                { label: 'S3-compatible', value: 's3' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full mt-1"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Ownership</label>
                        <Select
                            v-model="form.ownership"
                            :options="[
                                { label: 'School', value: 'school' },
                                { label: 'System', value: 'system' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full mt-1"
                            :disabled="Boolean(activeSchoolId) && form.ownership === 'school'"
                        />
                    </div>
                </div>
                <div v-else class="text-sm text-surface-500">
                    Driver: <strong>{{ form.driver }}</strong> · Ownership: <strong>{{ ownershipLabel(form.ownership) }}</strong>
                    (immutable after create)
                </div>

                <template v-if="isS3">
                    <div>
                        <label class="text-sm font-medium">Access key</label>
                        <InputText v-model="form.config.key as string" class="w-full mt-1" autocomplete="off" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">
                            Secret key
                            <span v-if="editing?.secrets_configured" class="text-xs text-surface-400">(leave blank to keep existing)</span>
                        </label>
                        <InputText v-model="form.secrets.secret" type="password" class="w-full mt-1" autocomplete="new-password" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm font-medium">Bucket</label>
                            <InputText v-model="form.config.bucket as string" class="w-full mt-1" />
                        </div>
                        <div>
                            <label class="text-sm font-medium">Region</label>
                            <InputText v-model="form.config.region as string" class="w-full mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Endpoint (optional)</label>
                        <InputText v-model="form.config.endpoint as string" class="w-full mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Base URL (optional)</label>
                        <InputText v-model="form.config.url as string" class="w-full mt-1" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.config.use_path_style_endpoint" :binary="true" inputId="pathStyle" />
                        <label for="pathStyle" class="text-sm">Path-style endpoint</label>
                    </div>
                    <div v-if="editing?.secrets_configured" class="flex items-center gap-2">
                        <Checkbox v-model="form.clear_secrets" :binary="true" inputId="clearSecrets" />
                        <label for="clearSecrets" class="text-sm text-red-600">Clear stored secrets</label>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="text-sm font-medium">Root path (optional)</label>
                        <InputText v-model="form.config.root as string" class="w-full mt-1" placeholder="Defaults to storage/app/private" />
                    </div>
                </template>

                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.enabled" inputId="enabled" />
                    <label for="enabled" class="text-sm">Enabled</label>
                </div>

                <Message v-if="testResult" :severity="testResult.success ? 'success' : 'error'" :closable="false">
                    {{ testResult.message }}
                </Message>
            </div>

            <template #footer>
                <div class="flex justify-between w-full gap-2">
                    <Button
                        label="Test connection"
                        severity="secondary"
                        :loading="testLoading"
                        :disabled="!canManage"
                        @click="runTest"
                    />
                    <div class="flex gap-2">
                        <Button label="Cancel" text @click="dialogVisible = false" />
                        <Button label="Save" :loading="saving" :disabled="!canManage || !form.name" @click="save" />
                    </div>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
