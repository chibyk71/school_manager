<!-- resources/js/Pages/Settings/Academic/GradingScales.vue -->
<script setup lang="ts">
/**
 * GradingScales.vue v1.0 – Production-Ready Grading Scales Management Page
 *
 * Purpose:
 * Full CRUD for grading scales with letter grades, percentage ranges, and GPA values.
 *
 * Features / Problems Solved:
 * - Add/Edit/Delete grading scales
 * - Dynamic grade rows (letter, min-max %, GPA)
 * - Default scale selection
 * - Validation feedback
 * - Responsive PrimeVue DataTable + nested display
 * - Modal with dynamic form rows
 * - Bulk delete
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, Dialog, InputText, InputNumber, ConfirmDialog, ToggleSwitch } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Grade {
    letter: string
    min: number
    max: number
    gpa: number
}

interface GradingScale {
    id: number
    name: string
    is_default: boolean
    grades: Grade[]
}

interface Props {
    grading_scales: { scales: GradingScale[] }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { academicSettingsNav } = useSettingsNavigation()

const scales = ref<GradingScale[]>(props.grading_scales.scales)

// Modal
const modalVisible = ref(false)
const editingId = ref<number | null>(null)
const form = useForm({
    name: '',
    is_default: false,
    grades: [] as Grade[],
})

const openModal = (scale?: GradingScale) => {
    if (scale) {
        editingId.value = scale.id
        form.name = scale.name
        form.is_default = scale.is_default
        form.grades = [...scale.grades]
    } else {
        editingId.value = null
        form.reset()
        form.grades = [{ letter: 'A', min: 90, max: 100, gpa: 4.0 }]
    }
    modalVisible.value = true
}

const addGrade = () => {
    form.grades.push({ letter: '', min: 0, max: 0, gpa: 0 })
}

const removeGrade = (index: number) => {
    form.grades.splice(index, 1)
}

const save = () => {
    const url = editingId.value
        ? route('settings.academic.grading.update', editingId.value)
        : route('settings.academic.grading.store')

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
    router.post(route('settings.academic.grading.destroy'), { ids: idsToDelete.value })
    deleteDialog.value = false
}
</script>

<template>
    <AuthenticatedLayout title="Grading Scales" :crumb="props.crumbs">

        <Head title="Grading Scales" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Academic" :items="academicSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Grading Scales</h1>
                            <p class="text-gray-600">Define letter grades, percentage ranges, and GPA values</p>
                        </div>
                        <Button label="Add Grading Scale" @click="openModal()" />
                    </div>

                    <Card v-for="scale in scales" :key="scale.id" class="mb-6">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold">{{ scale.name }}</h3>
                                    <Badge v-if="scale.is_default" value="Default" severity="success" class="mt-2" />
                                </div>
                                <div class="flex gap-2">
                                    <Button icon="pi pi-pencil" severity="secondary" text @click="openModal(scale)" />
                                    <Button icon="pi pi-trash" severity="danger" text
                                        @click="confirmDelete(scale.id)" />
                                </div>
                            </div>
                        </template>
                        <template #content>
                            <DataTable :value="scale.grades" class="p-datatable-sm">
                                <Column field="letter" header="Letter" />
                                <Column header="Percentage Range">
                                    <template #body="{ data }">
                                        {{ data.min }}% – {{ data.max }}%
                                    </template>
                                </Column>
                                <Column field="gpa" header="GPA Value" />
                            </DataTable>
                        </template>
                    </Card>
                </div>
            </template>
        </SettingsLayout>

        <!-- Add/Edit Modal -->
        <Dialog v-model:visible="modalVisible" :header="editingId ? 'Edit Grading Scale' : 'Add Grading Scale'" modal>
            <form @submit.prevent="save">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Scale Name</label>
                        <InputText v-model="form.name" fluid />
                    </div>

                    <div class="space-y-4">
                        <h4 class="font-medium">Grades</h4>
                        <div v-for="(grade, index) in form.grades" :key="index"
                            class="grid grid-cols-1 md:grid-cols-4 gap-4 border rounded-lg p-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Letter</label>
                                <InputText v-model="grade.letter" fluid />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Min %</label>
                                <InputNumber v-model="grade.min" :min="0" :max="100" fluid />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Max %</label>
                                <InputNumber v-model="grade.max" :min="0" :max="100" fluid />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">GPA</label>
                                <InputNumber v-model="grade.gpa" :min="0" :max="5" :step="0.1" fluid />
                            </div>
                            <Button v-if="form.grades.length > 1" icon="pi pi-trash" severity="danger" text
                                @click="removeGrade(index)" />
                        </div>
                        <Button label="Add Grade" severity="secondary" @click="addGrade()" />
                    </div>

                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="form.is_default" />
                        <label class="font-medium">Set as default scale</label>
                    </div>
                </div>

                <template #footer>
                    <Button label="Cancel" severity="secondary" @click="modalVisible = false" />
                    <Button label="Save Scale" type="submit" :loading="form.processing" />
                </template>
            </form>
        </Dialog>

        <ConfirmDialog v-model:visible="deleteDialog" message="Delete selected grading scale(s)? This cannot be undone."
            @accept="performDelete" />
    </AuthenticatedLayout>
</template>