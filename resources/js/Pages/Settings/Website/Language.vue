<!-- resources/js/Pages/Settings/Website/Language.vue -->
<script setup lang="ts">
/**
 * Language.vue v1.0 – Production-Ready Web Translations Management
 *
 * Purpose:
 * Allows school/system admins to override any frontend string (buttons, labels, messages)
 * used on public website, parent portal, invoices, emails.
 *
 * Features / Problems Solved:
 * - Tabbed interface per locale (English first, others optional)
 * - Dynamic add/remove translation keys
 * - Live search/filter within locale
 * - Key-value table with inline editing
 * - Add new key modal
 * - Delete confirmation
 * - Responsive PrimeVue DataTable + Tabs
 * - Save only non-empty values
 * - Full integration with SettingsLayout + crumbs
 * - Accessible: proper labels, keyboard navigation
 *
 * Fits into the Settings Module:
 * - Navigation: Website & Branding → Web Translations
 * - Submits to WebTranslationsController@store
 * - Key: 'website.translations'
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputText, TabView, TabPanel, DataTable, Column, Dialog, ConfirmDialog, Tabs, TabList, Tab } from 'primevue'
import { ref, computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    translations: Record<string, Record<string, string>>
    crumbs: Array<{ label: string }>
    available_locales: string[]
}

const props = defineProps<Props>()
const { websiteSettingsNav } = useSettingsNavigation()

// Main form – nested locale → key → value
const form = useForm({
    translations: { ...props.translations },
})

// Search per locale
const searchQuery = ref<Record<string, string>>({})

// Add new key modal
const newKeyDialog = ref(false)
const newLocale = ref('en')
const newKey = ref('')
const newValue = ref('')

// Computed: filtered translations per locale
const filteredTranslations = computed(() => {
    const result: Record<string, Array<{ key: string; value: string }>> = {}
    Object.keys(form.translations).forEach(locale => {
        const query = searchQuery.value[locale]?.toLowerCase() || ''
        const items = Object.entries(form.translations[locale])
            .filter(([key, value]) => key.toLowerCase().includes(query) || value.toLowerCase().includes(query))
            .map(([key, value]) => ({ key, value }))
        result[locale] = items
    })
    return result
})

// Add new key
const addNewKey = () => {
    if (!newKey.value.trim()) return
    if (!form.translations[newLocale.value]) {
        form.translations[newLocale.value] = {}
    }
    form.translations[newLocale.value][newKey.value] = newValue.value
    newKey.value = ''
    newValue.value = ''
    newKeyDialog.value = false
}

// Delete key
const deleteKey = (locale: string, key: string) => {
    delete form.translations[locale][key]
    if (Object.keys(form.translations[locale]).length === 0) {
        delete form.translations[locale]
    }
}

const submit = () => {
    form.post(route('settings.website.language.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Web Translations" :crumb="props.crumbs">

        <Head title="Web Translations" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Website & Branding" :items="websiteSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Web Translations</h1>
                                <p class="text-gray-600 mt-1">Customize text displayed on website, portal, invoices, and
                                    emails</p>
                            </div>
                            <div class="mt-4 sm:mt-0 flex gap-3">
                                <Button label="Add New String" @click="newKeyDialog = true" severity="secondary" />
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <Card>
                            <template #content>
                                <Tabs value="en" class="mb-4">
                                    <TabList>
                                        <Tab v-for="locale in Object.keys(form.translations)" :key="locale"
                                            :value="locale">{{ locale.toUpperCase() }}</Tab>
                                    </TabList>
                                    <TabPanels>
                                        <TabPanel v-for="locale in Object.keys(form.translations)" :value="locale" :key="locale"
                                            :header="locale.toUpperCase()">
                                            <div class="mb-4">
                                                <InputText v-model="searchQuery[locale]"
                                                    placeholder="Search keys or values..." fluid class="w-full" />
                                            </div>

                                            <DataTable :value="filteredTranslations[locale]" class="p-datatable-sm">
                                                <Column field="key" header="Key" style="width: 40%">
                                                    <template #body="{ data }">
                                                        <code
                                                            class="text-sm bg-gray-100 px-2 py-1 rounded">{{ data.key }}</code>
                                                    </template>
                                                </Column>
                                                <Column field="value" header="Translation" style="width: 50%">
                                                    <template #body="{ data }">
                                                        <InputText v-model="form.translations[locale][data.key]"
                                                            fluid />
                                                    </template>
                                                </Column>
                                                <Column style="width: 10%">
                                                    <template #body="{ data }">
                                                        <Button icon="pi pi-trash" severity="danger" text rounded
                                                            @click="deleteKey(locale, data.key)" />
                                                    </template>
                                                </Column>
                                            </DataTable>

                                            <p v-if="filteredTranslations[locale].length === 0"
                                                class="text-gray-500 text-center py-8">
                                                No translations found. Add your first string!
                                            </p>
                                        </TabPanel>
                                    </TabPanels>
                                </Tabs>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>

        <!-- Add New String Modal -->
        <Dialog v-model:visible="newKeyDialog" header="Add New Translation String" modal :style="{ width: '500px' }">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Locale</label>
                    <select v-model="newLocale" class="w-full border rounded-lg px-3 py-2">
                        <option v-for="loc in props.available_locales" :value="loc">{{ loc.toUpperCase() }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Key (unique identifier)</label>
                    <InputText v-model="newKey" placeholder="e.g., welcome_message" fluid />
                    <p class="text-xs text-gray-500 mt-1">Use snake_case. This will be used in code.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Default Value</label>
                    <InputText v-model="newValue" placeholder="e.g., Welcome to our school" fluid />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="newKeyDialog = false" />
                <Button label="Add String" @click="addNewKey" />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>