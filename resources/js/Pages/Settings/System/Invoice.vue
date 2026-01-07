<!-- resources/js/Pages/Settings/App/Invoice.vue -->
<script setup lang="ts">
/**
 * Invoice.vue v1.0 – Production-Ready Invoice Settings Page
 *
 * Purpose:
 * Full customization of invoice appearance, numbering, and content.
 * Clean grouped layout with live preview of next invoice number.
 *
 * Features / Problems Solved:
 * - Responsive PrimeVue form with logical sections
 * - Live invoice number preview as user types
 * - Custom invoice logo upload (separate from school logo)
 * - Template selection with thumbnails (future enhancement)
 * - Tax toggle with conditional fields
 * - Full accessibility and mobile optimization
 * - SettingsLayout + Sidebar + crumbs
 * - Matches your PreSkool template style
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputText, InputNumber, ToggleSwitch, Textarea, type FileUpload } from 'primevue'
import { computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        prefix: string
        next_number: number
        number_digits: number
        template: string
        due_days: number
        show_tax: boolean
        tax_rate: number
        tax_label: string
        notes: string
        terms: string
        show_logo: boolean
        logo_url?: string
    }
    preview: string
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { appSettingsNav } = useSettingsNavigation() // Adjust if you have separate group

const form = useForm({
    prefix: props.settings.prefix ?? 'INV',
    next_number: props.settings.next_number ?? 1,
    number_digits: props.settings.number_digits ?? 6,
    template: props.settings.template ?? 'modern',
    due_days: props.settings.due_days ?? 14,
    show_tax: props.settings.show_tax ?? true,
    tax_rate: props.settings.tax_rate ?? 7.5,
    tax_label: props.settings.tax_label ?? 'VAT',
    notes: props.settings.notes ?? '',
    terms: props.settings.terms ?? '',
    show_logo: props.settings.show_logo ?? true,
    invoice_logo: null as File | null,
})

// Live preview of next invoice number
const invoicePreview = computed(() => {
    return `${form.prefix}-${new Date().getFullYear()}${String(form.next_number).padStart(form.number_digits, '0')}`
})

const submit = () => {
    form.post(route('settings.app.invoice.store'), {
        preserveScroll: true,
        forceFormData: true, // For file upload
    })
}
</script>

<template>
    <AuthenticatedLayout title="Invoice Settings" :crumb="props.crumbs">

        <Head title="Invoice Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="App & Customization" :items="appSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Invoice Settings</h1>
                                <p class="text-gray-600 mt-1">Customize invoice numbering, template, and content</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Numbering -->
                        <Card class="mb-6">
                            <template #title>Invoice Numbering</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Prefix</label>
                                        <InputText v-model="form.prefix" fluid placeholder="INV" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Next Number</label>
                                        <InputNumber v-model="form.next_number" :min="1" fluid showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Digits</label>
                                        <InputNumber v-model="form.number_digits" :min="1" :max="10" fluid
                                            showButtons />
                                    </div>
                                </div>
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm font-medium text-gray-700">Next Invoice Number:</p>
                                    <code class="text-lg font-mono">{{ invoicePreview }}</code>
                                </div>
                            </template>
                        </Card>

                        <!-- Template & Branding -->
                        <Card class="mb-6">
                            <template #title>Template & Branding</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Template
                                            Style</label>
                                        <select v-model="form.template" class="w-full border rounded-lg px-3 py-2">
                                            <option value="modern">Modern</option>
                                            <option value="classic">Classic</option>
                                            <option value="minimal">Minimal</option>
                                            <option value="professional">Professional</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.show_logo" />
                                        <label class="text-sm font-medium">Show Logo on Invoice</label>
                                    </div>
                                </div>
                                <div v-if="form.show_logo" class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Invoice
                                        Logo</label>
                                    <div v-if="props.settings.logo_url" class="mb-4">
                                        <img :src="props.settings.logo_url"
                                            class="h-24 object-contain border rounded" />
                                    </div>
                                    <FileUpload v-model="form.invoice_logo" mode="basic" accept="image/*"
                                        class="w-full" />
                                    <p class="text-xs text-gray-500 mt-1">Recommended: transparent PNG, 300x100px</p>
                                </div>
                            </template>
                        </Card>

                        <!-- Payment Terms -->
                        <Card class="mb-6">
                            <template #title>Payment Terms</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Due Within
                                            (days)</label>
                                        <InputNumber v-model="form.due_days" :min="0" :max="365" fluid showButtons />
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.show_tax" />
                                        <label class="text-sm font-medium">Include Tax</label>
                                    </div>
                                </div>
                                <div v-if="form.show_tax" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                                        <InputNumber v-model="form.tax_rate" :min="0" :max="100" :step="0.1" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tax Label</label>
                                        <InputText v-model="form.tax_label" fluid placeholder="VAT" />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Notes & Terms -->
                        <Card>
                            <template #title>Notes & Terms</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Footer Notes</label>
                                        <Textarea v-model="form.notes" rows="3" fluid
                                            placeholder="Thank you for your business." />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Terms &
                                            Conditions</label>
                                        <Textarea v-model="form.terms" rows="5" fluid
                                            placeholder="Payment is due within 14 days..." />
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
