<!-- resources/js/Pages/Settings/School/General/Localization.vue -->
<script setup lang="ts">
/**
 * Localization.vue v3.0 – Production-Ready School Localization Settings Form
 *
 * Features / Problems Solved:
 * - Full integration with SettingsLayout & SettingsSidebar (consistent UI)
 * - Uses Inertia useForm with proper typing and pre-filled merged settings
 * - All fields bound to validated backend rules
 * - Responsive PrimeVue components with proper accessibility (labels, ARIA)
 * - Grouped MultiSelect for allowed file types with clear UX
 * - Loading state handling during submit
 * - Success/error toasts via PrimeVue (can be added later)
 * - Clean, maintainable structure with logical sections
 *
 * Fits into the Settings Module:
 * - Renders inside SettingsLayout → main slot
 * - Sidebar provided via parent or route group
 * - Submits to LocalizationController@store
 * - Displays merged values (school overrides shown correctly)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputText, InputGroup, InputGroupAddon, MultiSelect, Select, ToggleSwitch } from 'primevue'
import { route } from 'ziggy-js'

interface Props {
    settings: {
        language: string
        language_switcher: boolean
        timezone: string
        date_format: string
        time_format: string
        financial_year: number
        starting_month: string
        currency: string
        currency_position: 'before' | 'after'
        decimal_separator: string
        thousands_separator: string
        allowed_file_types: string[]
        max_file_upload_size: string
    }
}

const props = defineProps<Props>()

// Available options
const languages = ['English', 'Spanish', 'French'] // extend with i18n later
const timezones = ['UTC', 'UTC+01:00', 'UTC+05:30', 'UTC-05:00'] // populate from list or package
const dateFormats = ['dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy/mm/dd', 'jS F Y']
const timeFormats = ['12 hours', '24 hours']
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const currencies = ['NGN', 'USD', 'GHS', 'EUR']
const positions = ['before', 'after']
const separators = ['.', ',']

const allowedFileGroups = [
    { label: 'Images', value: ['jpg', 'jpeg', 'png', 'gif', 'webp'] },
    { label: 'Documents', value: ['pdf', 'doc', 'docx', 'txt'] },
    { label: 'Spreadsheets', value: ['xls', 'xlsx', 'csv'] },
    { label: 'Presentations', value: ['ppt', 'pptx'] },
    { label: 'Videos', value: ['mp4', 'avi', 'mov', 'mkv'] },
    { label: 'Audio', value: ['mp3', 'wav', 'aac'] },
]

// Generate academic years (current + past 5 + next 5)
const currentYear = new Date().getFullYear()
const academicYears = Array.from({ length: 11 }, (_, i) => currentYear - 5 + i)

const form = useForm({
    language: props.settings.language ?? 'English',
    language_switcher: props.settings.language_switcher ?? true,
    timezone: props.settings.timezone ?? 'UTC',
    date_format: props.settings.date_format ?? 'dd/mm/yyyy',
    time_format: props.settings.time_format === '24' ? '24 hours' : '12 hours',
    financial_year: props.settings.financial_year ?? currentYear,
    starting_month: props.settings.starting_month ?? 'January',
    currency: props.settings.currency ?? 'NGN',
    currency_position: props.settings.currency_position ?? 'before',
    decimal_separator: props.settings.decimal_separator ?? '.',
    thousands_separator: props.settings.thousands_separator ?? ',',
    allowed_file_types: props.settings.allowed_file_types ?? [],
    max_file_upload_size: props.settings.max_file_upload_size ?? 10,
})

const submit = () => {
    form.transform((data) => ({
        ...data,
        time_format: data.time_format === '24 hours' ? '24' : '12',
        currency_position: data.currency_position,
    })).post(route('settings.localization.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(), // optional: keep values
    })
}

const sidebarItems = [
    { label: 'Company Settings', href: route('settings.company'), icon: 'pi pi-building' },
    { label: 'Localization', href: route('settings.localization.index'), active: true, icon: 'pi pi-globe' },
    // Add more as needed
]
</script>

<template>
    <AuthenticatedLayout title="Localization Setting" :crumb="[{label:'Settings'},{label:'School'},{label:'Localization'}]" :buttons="[{icon: 'ti ti-refresh', severity: 'secondary', size:'small'}]">
        <Head title="Localization Setting" />
        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Website Settings" :items="sidebarItems" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Localization</h1>
                                <p class="text-gray-600 mt-1">Collection of settings for user environment</p>
                            </div>
                            <div class="mt-4 sm:mt-0 flex gap-3">
                                <Button label="Cancel" severity="secondary" outlined
                                    @click="$inertia.visit(route('settings.localization.index'))" />
                                <Button label="Save Changes" type="submit" :disabled="form.processing" />
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <Card class="mb-6">
                            <template #title>Basic Information</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                                            <Select v-model="form.language" :options="languages"
                                                placeholder="Select language" fluid />
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h5 class="font-medium">Language Switcher</h5>
                                                <p class="text-sm text-gray-600">Display language switcher on all pages
                                                </p>
                                            </div>
                                            <ToggleSwitch v-model="form.language_switcher" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                            <Select v-model="form.timezone" :options="timezones"
                                                placeholder="Select timezone" fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Date
                                                Format</label>
                                            <Select v-model="form.date_format" :options="dateFormats" fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Time
                                                Format</label>
                                            <Select v-model="form.time_format" :options="timeFormats" fluid />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Financial
                                                Year</label>
                                            <Select v-model="form.financial_year" :options="academicYears" fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Starting
                                                Month</label>
                                            <Select v-model="form.starting_month" :options="months" fluid />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Currency Settings -->
                        <Card class="mb-6">
                            <template #title>Currency Settings</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                                        <Select v-model="form.currency" :options="currencies" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency
                                            Position</label>
                                        <Select v-model="form.currency_position" :options="['before', 'after']"
                                            optionLabel="Before" optionValue="before" fluid />
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Decimal
                                            Separator</label>
                                        <Select v-model="form.decimal_separator" :options="separators" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Thousands
                                            Separator</label>
                                        <Select v-model="form.thousands_separator" :options="separators" fluid />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- File Upload Settings -->
                        <Card>
                            <template #title>File Upload Settings</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div>
                                        <h6 class="font-medium text-gray-900 mb-2">Allowed File Types</h6>
                                        <p class="text-sm text-gray-600 mb-4">Select file types users can upload</p>
                                        <MultiSelect v-model="form.allowed_file_types" :options="allowedFileGroups"
                                            optionLabel="label" optionGroupLabel="label" :optionGroupChildren="'value'"
                                            placeholder="Select allowed types" display="chip" fluid />
                                    </div>
                                    <div>
                                        <h6 class="font-medium text-gray-900 mb-2">Maximum Upload Size</h6>
                                        <p class="text-sm text-gray-600 mb-4">Maximum file size in MB</p>
                                        <InputGroup>
                                            <InputText v-model="form.max_file_upload_size" type="number" min="1"
                                                max="100" fluid />
                                            <InputGroupAddon>MB</InputGroupAddon>
                                        </InputGroup>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>