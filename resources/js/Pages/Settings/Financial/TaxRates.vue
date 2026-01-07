<!-- resources/js/Pages/Settings/Financial/TaxRates.vue -->
<script setup lang="ts">
/**
 * TaxRates.vue v1.0 – Production-Ready Multi-Tax Rate Management
 *
 * Purpose:
 * Full CRUD for tax rates with name, rate, type (percentage/fixed), default status.
 * Clean DataTable with inline actions.
 *
 * Features / Problems Solved:
 * - Add/Edit/Delete multiple tax rates
 * - Default tax selection (radio)
 * - Responsive PrimeVue DataTable
 * - Modal for add/edit
 * - Bulk delete
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, Dialog, InputText, InputNumber, Select, ConfirmDialog, ToggleSwitch } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface TaxRate {
    id: number
    name: string
    rate: number
    type: 'percentage' | 'fixed'
    is_default: boolean
}

interface Props {
    taxes: { rates: TaxRate[] }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { financialSettingsNav } = useSettingsNavigation()

const rates = ref<TaxRate[]>(props.taxes.rates)

// Modal
const modalVisible = ref(false)
const editingId = ref<number | null>(null)
const form = useForm({
    name: '',
    rate: 0,
    type: 'percentage' as 'percentage' | 'fixed',
    is_default: false,
})

const openModal = (rate?: TaxRate) => {
    if (rate) {
        editingId.value = rate.id
        form.name = rate.name
        form.rate = rate.rate
        form.type = rate.type
        form.is_default = rate.is_default
    } else {
        editingId.value = null
        form.reset()
    }
    modalVisible.value = true
}

const save = () => {
    const url = editingId.value
        ? route('settings.financial.taxes.update', editingId.value)
        : route('settings.financial.taxes.store')

    form.post(url, {
        onSuccess: () => {
            modalVisible.value = false
        },
    })
}

// Bulk delete
const deleteDialog = ref(false)
const idsToDelete = ref<number[]>([])

const confirmDelete = (ids: number | number[]) => {
    idsToDelete.value = Array.isArray(ids) ? ids : [ids]
    deleteDialog.value = true
}

const performDelete = () => {
    router.post(route('settings.financial.taxes.destroy'), {
        ids: idsToDelete.value,
    })
    deleteDialog.value = false
}
</script>

<template>
    <AuthenticatedLayout title="Tax Rates" :crumb="props.crumbs">

        <Head title="Tax Rates" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Financial" :items="financialSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Tax Rates</h1>
                            <p class="text-gray-600">Manage tax rates applied to fees and invoices</p>
                        </div>
                        <Button label="Add Tax Rate" @click="openModal()" />
                    </div>

                    <Card>
                        <template #content>
                            <DataTable :value="rates" class="p-datatable-sm">
                                <Column field="name" header="Name" />
                                <Column header="Rate">
                                    <template #body="{ data }">
                                        {{ data.rate }} {{ data.type === 'percentage' ? '%' : 'fixed' }}
                                    </template>
                                </Column>
                                <Column header="Default">
                                    <template #body="{ data }">
                                        <i v-if="data.is_default" class="pi pi-check text-green-600" />
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="{ data }">
                                        <Button icon="pi pi-pencil" severity="secondary" text
                                            @click="openModal(data)" />
                                        <Button icon="pi pi-trash" severity="danger" text
                                            @click="confirmDelete(data.id)" />
                                    </template>
                                </Column>
                            </DataTable>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <!-- Add/Edit Modal -->
        <Dialog v-model:visible="modalVisible" :header="editingId ? 'Edit Tax Rate' : 'Add Tax Rate'" modal>
            <form @submit.prevent="save">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Name</label>
                        <InputText v-model="form.name" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Rate</label>
                        <InputNumber v-model="form.rate" :min="0" fluid />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Type</label>
                        <Select v-model="form.type"
                            :options="[{ label: 'Percentage', value: 'percentage' }, { label: 'Fixed Amount', value: 'fixed' }]"
                            fluid />
                    </div>
                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="form.is_default" />
                        <label>Set as default tax rate</label>
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancel" severity="secondary" @click="modalVisible = false" />
                    <Button label="Save" type="submit" :loading="form.processing" />
                </template>
            </form>
        </Dialog>

        <ConfirmDialog v-model:visible="deleteDialog" message="Delete selected tax rate(s)? This cannot be undone."
            @accept="performDelete" />
    </AuthenticatedLayout>
</template>
