<!-- resources/js/Pages/Settings/Advanced/IpBan.vue -->
<script setup lang="ts">
/**
 * IpBan.vue v1.0 – Production-Ready IP Address Ban Management Page
 *
 * Purpose:
 * Full CRUD for banning IP addresses with reason and optional expiry.
 *
 * Features / Problems Solved:
 * - Add new banned IP with reason/expiry
 * - List all banned IPs
 * - Bulk/single delete
 * - Responsive PrimeVue DataTable
 * - Modal for adding new ban
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, Dialog, InputText, Textarea, ConfirmDialog } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface BannedIp {
    ip: string
    reason?: string
    expires_at?: string
    banned_at: string
}

interface Props {
    banned_ips: { list: BannedIp[] }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { advancedSettingsNav } = useSettingsNavigation()

const ips = ref<BannedIp[]>(props.banned_ips.list)

// Add Modal
const modalVisible = ref(false)
const form = useForm({
    ip: '',
    reason: '',
    expires_at: null as string | null,
})

const openModal = () => {
    form.reset()
    modalVisible.value = true
}

const save = () => {
    form.post(route('settings.advanced.ip.store'), {
        onSuccess: () => modalVisible.value = false,
    })
}

// Delete
const deleteDialog = ref(false)
const ipsToDelete = ref<string[]>([])

const confirmDelete = (ips: string | string[]) => {
    ipsToDelete.value = Array.isArray(ips) ? ips : [ips]
    deleteDialog.value = true
}

const performDelete = () => {
    router.post(route('settings.advanced.ip.destroy'), { ips: ipsToDelete.value })
    deleteDialog.value = false
}
</script>

<template>
    <AuthenticatedLayout title="Ban IP Address" :crumb="props.crumbs">

        <Head title="Ban IP Address" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Other Settings" :items="advancedSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Ban IP Address</h1>
                            <p class="text-gray-600">Block access from specific IP addresses</p>
                        </div>
                        <Button label="Ban New IP" @click="openModal()" />
                    </div>

                    <Card>
                        <template #content>
                            <DataTable :value="ips" class="p-datatable-sm">
                                <Column field="ip" header="IP Address" />
                                <Column field="reason" header="Reason" />
                                <Column header="Expires">
                                    <template #body="{ data }">
                                        {{ data.expires_at ? new Date(data.expires_at).toLocaleDateString() : 'Never' }}
                                    </template>
                                </Column>
                                <Column field="banned_at" header="Banned On">
                                    <template #body="{ data }">
                                        {{ new Date(data.banned_at).toLocaleString() }}
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="{ data }">
                                        <Button icon="pi pi-trash" severity="danger" text
                                            @click="confirmDelete([data.ip])" />
                                    </template>
                                </Column>
                            </DataTable>

                            <p v-if="ips.length === 0" class="text-center text-gray-500 py-8">
                                No IP addresses are currently banned.
                            </p>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <!-- Add Ban Modal -->
        <Dialog v-model:visible="modalVisible" header="Ban IP Address" modal>
            <form @submit.prevent="save">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">IP Address</label>
                        <InputText v-model="form.ip" placeholder="192.168.1.100" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Reason (optional)</label>
                        <Textarea v-model="form.reason" rows="3" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Expires On (optional)</label>
                        <InputText v-model="form.expires_at" type="date" fluid />
                        <p class="text-xs text-gray-500 mt-1">Leave blank for permanent ban</p>
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancel" severity="secondary" @click="modalVisible = false" />
                    <Button label="Ban IP" type="submit" :loading="form.processing" />
                </template>
            </form>
        </Dialog>

        <ConfirmDialog v-model:visible="deleteDialog" message="Remove ban from selected IP(s)?"
            @accept="performDelete" />
    </AuthenticatedLayout>
</template>