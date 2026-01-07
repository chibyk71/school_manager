<!-- resources/js/Pages/Settings/System/Gdpr.vue -->
<script setup lang="ts">
/**
 * Gdpr.vue v1.0 – Production-Ready GDPR & Cookie Consent Configuration Page
 *
 * Purpose:
 * Full customization of the cookie consent banner shown to users.
 * Live preview of banner appearance and position.
 *
 * Features / Problems Solved:
 * - Rich text editor for content (PrimeVue Editor or Textarea)
 * - Position selector with visual preview
 * - Conditional button/link fields
 * - Live banner preview in corner of screen
 * - Responsive PrimeVue form
 * - Full accessibility and mobile optimization
 * - SettingsLayout + Sidebar + crumbs
 * - Matches modern compliance banners (Cookiebot, OneTrust style)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, ToggleSwitch, InputText, Textarea, Select } from 'primevue'
import { computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        content_text: string
        position: string
        show_accept_button: boolean
        accept_button_text: string
        show_decline_button: boolean
        decline_button_text: string
        show_link: boolean
        link_text: string
        link_url: string
    }
    positions: string[]
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { appSettingsNav } = useSettingsNavigation()

const form = useForm({
    content_text: props.settings.content_text ?? 'We use cookies to enhance your experience...',
    position: props.settings.position ?? 'bottom',
    show_accept_button: props.settings.show_accept_button ?? true,
    accept_button_text: props.settings.accept_button_text ?? 'Accept All',
    show_decline_button: props.settings.show_decline_button ?? true,
    decline_button_text: props.settings.decline_button_text ?? 'Decline',
    show_link: props.settings.show_link ?? true,
    link_text: props.settings.link_text ?? 'Privacy Policy',
    link_url: props.settings.link_url ?? 'https://yourschool.com/privacy',
})

// Live preview styles
const previewStyle = computed(() => {
    const pos = form.position
    const styles: Record<string, string> = {
        'top': 'top-4 left-1/2 -translate-x-1/2',
        'bottom': 'bottom-4 left-1/2 -translate-x-1/2',
        'left': 'left-4 top-1/2 -translate-y-1/2',
        'right': 'right-4 top-1/2 -translate-y-1/2',
        'top-left': 'top-4 left-4',
        'top-right': 'top-4 right-4',
        'bottom-left': 'bottom-4 left-4',
        'bottom-right': 'bottom-4 right-4',
    }
    return styles[pos] ?? styles['bottom']
})

const submit = () => {
    form.post(route('settings.system.gdpr.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="GDPR & Cookies" :crumb="props.crumbs">

        <Head title="GDPR & Cookie Consent" />

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
                                <h1 class="text-2xl font-bold text-gray-900">GDPR & Cookies</h1>
                                <p class="text-gray-600 mt-1">Configure cookie consent banner for compliance</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <Card class="mb-6">
                            <template #title>Banner Content</template>
                            <template #content>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Text</label>
                                    <Textarea v-model="form.content_text" rows="6" fluid />
                                    <p class="text-xs text-gray-500 mt-1">Supports HTML for links and formatting</p>
                                </div>
                            </template>
                        </Card>

                        <Card class="mb-6">
                            <template #title>Position & Buttons</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Banner
                                            Position</label>
                                        <Select v-model="form.position" :options="props.positions" fluid />
                                    </div>
                                    <div class="space-y-4">
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.show_accept_button" />
                                            <label class="text-sm font-medium">Show Accept Button</label>
                                        </div>
                                        <InputText v-if="form.show_accept_button" v-model="form.accept_button_text"
                                            fluid />

                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.show_decline_button" />
                                            <label class="text-sm font-medium">Show Decline Button</label>
                                        </div>
                                        <InputText v-if="form.show_decline_button" v-model="form.decline_button_text"
                                            fluid />

                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.show_link" />
                                            <label class="text-sm font-medium">Show Privacy Link</label>
                                        </div>
                                        <div v-if="form.show_link" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <InputText v-model="form.link_text" fluid />
                                            <InputText v-model="form.link_url" type="url" fluid />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </form>

                    <!-- Live Preview Banner -->
                    <div class="fixed z-50 pointer-events-none" :class="previewStyle">
                        <div class="bg-gray-900 text-white p-4 rounded-lg shadow-2xl max-w-sm pointer-events-auto">
                            <div class="text-sm" v-html="form.content_text"></div>
                            <div class="flex gap-2 mt-4">
                                <Button v-if="form.show_accept_button" :label="form.accept_button_text" size="small" />
                                <Button v-if="form.show_decline_button" :label="form.decline_button_text"
                                    severity="secondary" size="small" outlined />
                                <a v-if="form.show_link" :href="form.link_url" class="text-sm underline">{{
                                    form.link_text }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
