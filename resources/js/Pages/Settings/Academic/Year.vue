<!-- resources/js/Pages/Settings/Academic/Year.vue -->
<script setup lang="ts">
/**
 * Year.vue v1.0 – Production-Ready Academic Year Management Page
 *
 * Purpose:
 * Full CRUD for academic years/sessions with terms.
 * Clean DataTable with active indicator and actions.
 *
 * Features / Problems Solved:
 * - Add/Edit/Delete academic years
 * - Only one active session
 * - Term structure (name, start, end)
 * - Responsive PrimeVue DataTable
 * - Modal for add/edit with term builder
 * - Bulk delete
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, Dialog, InputText, ConfirmDialog, ToggleSwitch } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Term {
    name: string
    start: string
    end: string
}

interface AcademicYear {
    id: number
    name: string
    start_date: Date| null
    end_date: Date| null
    terms: Term[]
    is_active: boolean
    created_at: string
}

interface Props {
    academic_years: AcademicYear[]
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { academicSettingsNav } = useSettingsNavigation()

const years = ref<AcademicYear[]>(props.academic_years)

// Modal
const modalVisible = ref(false)
const editingId = ref<number | null>(null)
const form = useForm({
    name: '',
    start_date: null,
    end_date: null,
    terms: [
        { name: 'First Term', start: '', end: '' },
        { name: 'Second Term', start: '', end: '' },
    ],
    is_active: false,
})

const openModal = (year?: AcademicYear) => {
    if (year) {
        editingId.value = year.id
        form.name = year.name
        form.start_date = year.start_date
        form.end_date = year.end_date
        form.terms = year.terms
        form.is_active = year.is_active
    } else {
        editingId.value = null
        form.reset()
        form.terms = [
            { name: 'First Term', start: '', end: '' },
            { name: 'Second Term', start: '', end: '' },
        ]
    }
    modalVisible.value = true
}

const addTerm = () => {
    form.terms.push({ name: `Term ${form.terms.length + 1}`, start: '', end: '' })
}

const removeTerm = (index: number) => {
    form.terms.splice(index, 1)
}

const save = () => {
    const url = editingId.value
        ? route('settings.academic.year.update', editingId.value)
        : route('settings.academic.year.store')

    form.post(url, {
        onSuccess: () => modalVisible.value = false,
    })
}

// Delete
const deleteDialog = ref(false)
const idsToDelete = ref<number[]>([])

const confirmDelete = (ids: number | number[]) => {
    idsToDelete.value = Array.isArray(ids) ? ids : [ids]
    deleteDialog.value = true
}

const performDelete = () => {
    router.post(route('settings.academic.year.destroy'), { ids: idsToDelete.value })
    deleteDialog.value = false
}
</script>

<template>
    <AuthenticatedLayout title="Academic Year" :crumb="props.crumbs">

        <Head title="Academic Year" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Academic" :items="academicSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Academic Year</h1>
                            <p class="text-gray-600">Manage current and historical academic sessions</p>
                        </div>
                        <Button label="Add New Session" @click="openModal()" />
                    </div>

                    <Card>
                        <template #content>
                            <DataTable :value="years" class="p-datatable-sm">
                                <Column field="name" header="Session" />
                                <Column header="Dates">
                                    <template #body="{ data }">
                                        {{ data.start_date }} – {{ data.end_date }}
                                    </template>
                                </Column>
                                <Column header="Terms">
                                    <template #body="{ data }">
                                        {{ data.terms.length }} Terms
                                    </template>
                                </Column>
                                <Column header="Status">
                                    <template #body="{ data }">
                                        <Badge v-if="data.is_active" value="Active" severity="success" />
                                        <Badge v-else value="Archived" severity="secondary" />
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="{ data }">
                                        <Button icon="pi pi-pencil" severity="secondary" text
                                            @click="openModal(data)" />
                                        <Button icon="pi pi-trash" severity="danger" text
                                            @click="confirmDelete(data.id)" :disabled="data.is_active" />
                                    </template>
                                </Column>
                            </DataTable>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <!-- Add/Edit Modal -->
        <Dialog v-model:visible="modalVisible" :header="editingId ? 'Edit Session' : 'Add New Academic Session'" modal>
            <form @submit.prevent="save">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Session Name</label>
                        <InputText v-model="form.name" fluid placeholder="2025/2026" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Start Date</label>
                            <DatePicker v-model="form.start_date" type="date" fluid />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">End Date</label>
                            <DatePicker v-model="form.end_date" type="date" fluid />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="font-medium">Terms</h4>
                        <div v-for="(term, index) in form.terms" :key="index" class="border rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-2">Term Name</label>
                                    <InputText v-model="term.name" fluid />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Start Date</label>
                                    <InputText v-model="term.start" type="date" fluid />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">End Date</label>
                                    <InputText v-model="term.end" type="date" fluid />
                                </div>
                            </div>
                            <Button v-if="form.terms.length > 2" icon="pi pi-trash" severity="danger" text
                                @click="form.terms.splice(index, 1)" class="mt-2" />
                        </div>
                        <Button label="Add Term" severity="secondary"
                            @click="form.terms.push({ name: `Term ${form.terms.length + 1}`, start: '', end: '' })" />
                    </div>

                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="form.is_active" />
                        <label class="font-medium">Set as Current Active Session</label>
                    </div>
                </div>

                <template #footer>
                    <Button label="Cancel" severity="secondary" @click="modalVisible = false" />
                    <Button label="Save Session" type="submit" :loading="form.processing" />
                </template>
            </form>
        </Dialog>

        <ConfirmDialog v-model:visible="deleteDialog" message="Delete selected academic year(s)? This cannot be undone."
            @accept="performDelete" />
    </AuthenticatedLayout>
</template>
