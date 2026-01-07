<!-- resources/js/Pages/Settings/Advanced/BackupRestore.vue -->
<script setup lang="ts">
/**
 * BackupRestore.vue v1.0 – Production-Ready Backup & Restore Page
 *
 * Purpose:
 * Lists existing backups with size/date, allows create/download/delete.
 *
 * Features / Problems Solved:
 * - Clean DataTable with backup list
 * - Create backup button
 * - Download/Delete actions
 * - Human-readable file size
 * - Responsive layout
 * - Full PrimeVue integration
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, ConfirmDialog } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Backup {
    path: string
    name: string
    size: number
    date: number
}

interface Props {
    backups: Backup[]
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { advancedSettingsNav } = useSettingsNavigation()

const formatSize = (bytes: number) => {
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(1024))
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`
}

const formatDate = (timestamp: number) => {
    return new Date(timestamp * 1000).toLocaleString()
}

// Delete
const deleteDialog = ref(false)
const backupToDelete = ref<string | null>(null)

const confirmDelete = (name: string) => {
    backupToDelete.value = name
    deleteDialog.value = true
}

const performDelete = () => {
    if (backupToDelete.value) {
        router.delete(route('settings.advanced.backup.destroy', backupToDelete.value))
    }
    deleteDialog.value = false
}
</script>

<template>
    <AuthenticatedLayout title="Backup & Restore" :crumb="props.crumbs">

        <Head title="Backup & Restore" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Other Settings" :items="advancedSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Backup & Restore</h1>
                            <p class="text-gray-600">Manage database and files backups</p>
                        </div>
                        <Button label="Create Backup"
                            @click="$inertia.post(route('settings.advanced.backup.create'))" />
                    </div>

                    <Card>
                        <template #content>
                            <DataTable :value="props.backups" class="p-datatable-sm">
                                <Column field="name" header="File Name" />
                                <Column header="Size">
                                    <template #body="{ data }">
                                        {{ formatSize(data.size) }}
                                    </template>
                                </Column>
                                <Column header="Created">
                                    <template #body="{ data }">
                                        {{ formatDate(data.date) }}
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="{ data }">
                                        <Button icon="pi pi-download" severity="secondary" text
                                            :href="route('settings.advanced.backup.download', data.name)" />
                                        <Button icon="pi pi-trash" severity="danger" text
                                            @click="confirmDelete(data.name)" />
                                    </template>
                                </Column>
                            </DataTable>

                            <p v-if="props.backups.length === 0" class="text-center text-gray-500 py-8">
                                No backups found. Click "Create Backup" to generate one.
                            </p>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <ConfirmDialog v-model:visible="deleteDialog" message="Delete this backup permanently?"
            @accept="performDelete" />
    </AuthenticatedLayout>
</template>