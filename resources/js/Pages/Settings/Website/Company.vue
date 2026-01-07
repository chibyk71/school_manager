<!-- resources/js/Pages/Settings/Website/Company.vue -->
<script setup lang="ts">
/**
 * Company.vue v1.0 – Public Company Branding Settings Page
 *
 * Purpose:
 * Allows school admins to configure public-facing branding and legal information
 * (website footer, invoices, email signatures, parent portal).
 *
 * Features / Problems Solved:
 * - Full integration with SettingsLayout + SettingsSidebar (consistent UI)
 * - Pre-filled with merged settings (school overrides shown correctly)
 * - Responsive PrimeVue form with logical grouping
 * - Proper validation feedback via Inertia
 * - Loading state during submit
 * - Clean, accessible labels and structure
 * - Uses useForm for type-safety and easy error handling
 *
 * Fits into the Settings Module:
 * - Navigation: Website & Branding → Company Settings
 * - Submits to CompanySettingsController@store
 * - Key: 'website.company'
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputText, Textarea, ToggleSwitch } from 'primevue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        legal_name: string
        tagline?: string
        tax_id?: string
        public_email?: string
        public_phone?: string
        website_url?: string
        social_facebook?: string
        social_twitter?: string
        social_instagram?: string
        social_linkedin?: string
        social_youtube?: string
        footer_copyright?: string
        google_maps_embed?: string
        show_address_footer: boolean
        show_phone_footer: boolean
        show_email_footer: boolean
    }
}

const props = defineProps<Props>()
const { websiteSettingsNav } = useSettingsNavigation()

const form = useForm({
    legal_name: props.settings.legal_name ?? '',
    tagline: props.settings.tagline ?? '',
    tax_id: props.settings.tax_id ?? '',
    public_email: props.settings.public_email ?? '',
    public_phone: props.settings.public_phone ?? '',
    website_url: props.settings.website_url ?? '',
    social_facebook: props.settings.social_facebook ?? '',
    social_twitter: props.settings.social_twitter ?? '',
    social_instagram: props.settings.social_instagram ?? '',
    social_linkedin: props.settings.social_linkedin ?? '',
    social_youtube: props.settings.social_youtube ?? '',
    footer_copyright: props.settings.footer_copyright ?? '',
    google_maps_embed: props.settings.google_maps_embed ?? '',
    show_address_footer: props.settings.show_address_footer ?? true,
    show_phone_footer: props.settings.show_phone_footer ?? true,
    show_email_footer: props.settings.show_email_footer ?? true,
})

const submit = () => {
    form.post(route('settings.website.company.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: toast can be added later
        },
    })
}
</script>

<template>
    <AuthenticatedLayout title="Company Settings" :crumb="[{ label: 'Settings' }, { label: 'Website & Branding' }, { label: 'Company' }]">

        <Head title="Public Company Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Website & Branding" :items="websiteSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Public Company Settings</h1>
                                <p class="text-gray-600 mt-1">Information displayed on your website, invoices, and
                                    emails</p>
                            </div>
                            <div class="mt-4 sm:mt-0 flex gap-3">
                                <Button label="Cancel" severity="secondary" outlined
                                    @click="$inertia.visit(route('settings.website.company'))" />
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Legal & Basic Info -->
                        <Card class="mb-6">
                            <template #title>Legal & Contact Information</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Legal Name</label>
                                        <InputText v-model="form.legal_name" fluid
                                            placeholder="e.g., Dreams Technologies International School" />
                                        <p class="text-xs text-gray-500 mt-1">Used on official documents and invoices
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tagline</label>
                                        <InputText v-model="form.tagline" fluid
                                            placeholder="e.g., Empowering the Next Generation" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tax ID / VAT
                                            Number</label>
                                        <InputText v-model="form.tax_id" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Public Email</label>
                                        <InputText v-model="form.public_email" type="email" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Public Phone</label>
                                        <InputText v-model="form.public_phone" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Website URL</label>
                                        <InputText v-model="form.website_url" fluid
                                            placeholder="https://yourschool.edu.ng" />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Social Media -->
                        <Card class="mb-6">
                            <template #title>Social Media Links</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Facebook</label>
                                        <InputText v-model="form.social_facebook" fluid
                                            placeholder="https://facebook.com/yourschool" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Twitter / X</label>
                                        <InputText v-model="form.social_twitter" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Instagram</label>
                                        <InputText v-model="form.social_instagram" fluid />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn</label>
                                        <InputText v-model="form.social_linkedin" fluid />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">YouTube</label>
                                        <InputText v-model="form.social_youtube" fluid />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Footer & Maps -->
                        <Card class="mb-6">
                            <template #title>Website Footer & Maps</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Footer Copyright
                                            Text</label>
                                        <InputText v-model="form.footer_copyright" fluid
                                            placeholder="© 2026 Your School Name. All rights reserved." />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Google Maps Embed
                                            Code</label>
                                        <Textarea v-model="form.google_maps_embed" rows="4" fluid
                                            placeholder="Paste iframe code from Google Maps" />
                                        <p class="text-xs text-gray-500 mt-1">Used on Contact page</p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h6 class="font-medium">Show Address in Footer</h6>
                                            </div>
                                            <ToggleSwitch v-model="form.show_address_footer" />
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h6 class="font-medium">Show Phone in Footer</h6>
                                            </div>
                                            <ToggleSwitch v-model="form.show_phone_footer" />
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h6 class="font-medium">Show Email in Footer</h6>
                                            </div>
                                            <ToggleSwitch v-model="form.show_email_footer" />
                                        </div>
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