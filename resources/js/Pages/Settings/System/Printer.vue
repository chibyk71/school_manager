<!-- resources/js/Pages/Settings/App/Printer.vue -->
<script setup lang="ts">
/**
 * Printer.vue v1.0 – Production-Ready Thermal/Receipt Printer Settings Page
 *
 * Purpose:
 * Full customization of receipt/ID card printing: paper size, margins, fonts, logos,
 * barcode, header/footer text.
 *
 * Features / Problems Solved:
 * - Responsive PrimeVue form with logical sections
 * - Live preview of printable area (width + margins)
 * - Support for 58mm and 80mm thermal printers
 * - Custom header logo upload
 * - Barcode toggle with type selection
 * - DPI and font size controls
 * - Full accessibility and mobile optimization
 * - SettingsLayout + Sidebar + crumbs
 * - Matches your PreSkool template style
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputNumber, ToggleSwitch, InputText, Textarea, type FileUpload } from 'primevue'
import { computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        paper_width: number
        margin_top: number
        margin_bottom: number
        margin_left: number
        margin_right: number
        dpi: number
        font_size: number
        show_school_logo: boolean
        show_receipt_header: boolean
        header_text: string
        footer_text: string
        show_barcode: boolean
        barcode_type: string
        header_logo_url?: string
    }
    paper_sizes: number[]
    barcode_types: string[]
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { appSettingsNav } = useSettingsNavigation()

const form = useForm({
    paper_width: props.settings.paper_width ?? 80,
    margin_top: props.settings.margin_top ?? 5,
    margin_bottom: props.settings.margin_bottom ?? 5,
    margin_left: props.settings.margin_left ?? 3,
    margin_right: props.settings.margin_right ?? 3,
    dpi: props.settings.dpi ?? 203,
    font_size: props.settings.font_size ?? 10,
    show_school_logo: props.settings.show_school_logo ?? true,
    show_receipt_header: props.settings.show_receipt_header ?? true,
    header_text: props.settings.header_text ?? 'Official Receipt',
    footer_text: props.settings.footer_text ?? 'Thank you for your payment',
    show_barcode: props.settings.show_barcode ?? true,
    barcode_type: props.settings.barcode_type ?? 'CODE128',
    header_logo: null as File | null,
})

// Live preview calculations
const printableWidth = computed(() => {
    return form.paper_width - form.margin_left - form.margin_right
})

const submit = () => {
    form.post(route('settings.app.printer.store'), {
        preserveScroll: true,
        forceFormData: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Printer Settings" :crumb="props.crumbs">

        <Head title="Printer Settings" />

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
                                <h1 class="text-2xl font-bold text-gray-900">Printer Settings</h1>
                                <p class="text-gray-600 mt-1">Configure thermal printer for receipts and ID cards</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Paper & Margins -->
                        <Card class="mb-6">
                            <template #title>Paper Size & Margins</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Paper Width
                                            (mm)</label>
                                        <select v-model="form.paper_width" class="w-full border rounded-lg px-3 py-2">
                                            <option v-for="size in props.paper_sizes" :value="size">{{ size }}mm
                                            </option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Common thermal printer sizes: 58mm or 80mm
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">DPI</label>
                                        <select v-model="form.dpi" class="w-full border rounded-lg px-3 py-2">
                                            <option value="203">203 DPI (standard)</option>
                                            <option value="300">300 DPI (high quality)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Top Margin
                                            (mm)</label>
                                        <InputNumber v-model="form.margin_top" :min="0" :max="50" fluid showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Bottom Margin
                                            (mm)</label>
                                        <InputNumber v-model="form.margin_bottom" :min="0" :max="50" fluid
                                            showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Left Margin
                                            (mm)</label>
                                        <InputNumber v-model="form.margin_left" :min="0" :max="20" fluid showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Right Margin
                                            (mm)</label>
                                        <InputNumber v-model="form.margin_right" :min="0" :max="20" fluid showButtons />
                                    </div>
                                </div>

                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm font-medium text-gray-700">Printable Area:</p>
                                    <p class="text-lg font-mono">{{ printableWidth }}mm wide</p>
                                </div>
                            </template>
                        </Card>

                        <!-- Content & Branding -->
                        <Card class="mb-6">
                            <template #title>Content & Branding</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.show_school_logo" />
                                            <label class="text-sm font-medium">Show School Logo</label>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.show_receipt_header" />
                                            <label class="text-sm font-medium">Show Header Text</label>
                                        </div>
                                    </div>

                                    <div v-if="form.show_receipt_header">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Header Text</label>
                                        <InputText v-model="form.header_text" fluid placeholder="Official Receipt" />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Footer Text</label>
                                        <Textarea v-model="form.footer_text" rows="3" fluid
                                            placeholder="Thank you for your payment" />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Font Size
                                            (pt)</label>
                                        <InputNumber v-model="form.font_size" :min="8" :max="16" fluid showButtons />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Header
                                            Logo</label>
                                        <div v-if="props.settings.header_logo_url" class="mb-4">
                                            <img :src="props.settings.header_logo_url"
                                                class="h-20 object-contain border rounded" />
                                        </div>
                                        <FileUpload v-model="form.header_logo" accept="image/*" class="w-full" />
                                        <p class="text-xs text-gray-500 mt-1">Recommended: black & white, 300x80px</p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Barcode -->
                        <Card>
                            <template #title>Barcode</template>
                            <template #content>
                                <div class="flex items-center gap-3 mb-4">
                                    <ToggleSwitch v-model="form.show_barcode" />
                                    <label class="text-sm font-medium">Include Barcode on Receipt</label>
                                </div>
                                <div v-if="form.show_barcode">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Barcode Type</label>
                                    <select v-model="form.barcode_type" class="w-full border rounded-lg px-3 py-2">
                                        <option v-for="type in props.barcode_types" :value="type">{{ type }}</option>
                                    </select>
                                </div>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>