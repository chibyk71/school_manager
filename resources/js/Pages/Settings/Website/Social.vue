<!-- resources/js/Pages/Settings/Website/Social.vue -->
<script setup lang="ts">
/**
 * Social.vue v2.0 – Production-Ready Social Authentication Settings (Template-Inspired Card Design)
 *
 * Purpose:
 * Replicates the clean, card-based layout from your PreSkool template inspiration:
 * - Horizontal cards with provider logo, name, description, status badge
 * - "Connected" / "Not Connected" badge
 * - Toggle switch for enable/disable
 * - "View Integration" button opens a modal to input/edit client_id + client_secret
 * - Modal emits saved data → merged into main form
 * - Main Save button disabled until form is dirty
 * - No accordion – matches your screenshot exactly
 *
 * Features / Problems Solved:
 * - Beautiful, modern card layout matching template (Facebook, Twitter, Google cards)
 * - Modal-based credential entry for better UX and security (secrets not visible on main page)
 * - Form only becomes dirty when credentials are saved from modal
 * - Submit button intelligently disabled when no changes
 * - Status badge updates live based on credentials presence
 * - Copyable redirect URIs inside modal
 * - Responsive grid (1-3 columns)
 * - Full accessibility and PrimeVue best practices
 * - Uses your helpers via controller (merged settings, global defaults supported)
 *
 * Fits into the Settings Module:
 * - Navigation: Website & Branding → Social Authentication
 * - Controller: SocialAuthSettingsController (unchanged)
 * - Modal: SocialProviderModal.vue (new component)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, Badge, ToggleSwitch } from 'primevue'
import { ref } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import SocialProviderModal from '@/Components/Modals/Create/SocialProviderModal.vue'

interface Provider {
    key: string
    name: string
    icon: string
    docs: string
    enabled: boolean
    client_id?: string
    client_secret?: string
    // Apple extra fields
    team_id?: string
    key_id?: string
    private_key?: string
}

interface Props {
    settings: Record<string, any>
    crumbs: Array<{ label: string }>
    redirect_uris: { login: string; register: string }
    providers: Record<string, { name: string; icon: string; docs: string }>
}

const props = defineProps<Props>()
const { websiteSettingsNav } = useSettingsNavigation()

// Main form – starts with current merged settings
const form = useForm({ ...props.settings })

// Track if form has changes
const isDirty = ref(false)

// Modal state
const modalVisible = ref(false)
const currentProvider = ref<Provider | null>(null)

// Provider list for display
const providerList = ref<Provider[]>([
    {
        key: 'facebook',
        name: 'Facebook',
        icon: 'pi pi-facebook text-3xl text-blue-600',
        docs: props.providers.facebook.docs,
        enabled: form.facebook_enabled ?? false,
        client_id: form.facebook_client_id,
        client_secret: form.facebook_client_secret,
    },
    {
        key: 'twitter',
        name: 'Twitter',
        icon: 'pi pi-twitter text-3xl text-sky-500',
        docs: props.providers.twitter.docs,
        enabled: form.twitter_enabled ?? false,
        client_id: form.twitter_client_id,
        client_secret: form.twitter_client_secret,
    },
    {
        key: 'google',
        name: 'Google',
        icon: 'pi pi-google text-3xl text-red-500',
        docs: props.providers.google.docs,
        enabled: form.google_enabled ?? false,
        client_id: form.google_client_id,
        client_secret: form.google_client_secret,
    },
    {
        key: 'microsoft',
        name: 'Microsoft',
        icon: 'pi pi-microsoft text-3xl text-blue-700',
        docs: props.providers.microsoft.docs,
        enabled: form.microsoft_enabled ?? false,
        client_id: form.microsoft_client_id,
        client_secret: form.microsoft_client_secret,
    },
    {
        key: 'apple',
        name: 'Sign in with Apple',
        icon: 'pi pi-apple text-3xl text-black',
        docs: props.providers.apple.docs,
        enabled: form.apple_enabled ?? false,
        client_id: form.apple_client_id,
        team_id: form.apple_team_id,
        key_id: form.apple_key_id,
        private_key: form.apple_private_key,
    },
    {
        key: 'linkedin',
        name: 'LinkedIn',
        icon: 'pi pi-linkedin text-3xl text-blue-700',
        docs: props.providers.linkedin.docs,
        enabled: form.linkedin_enabled ?? false,
        client_id: form.linkedin_client_id,
        client_secret: form.linkedin_client_secret,
    },
])

// Open modal for editing credentials
const openProviderModal = (provider: Provider) => {
    currentProvider.value = provider
    modalVisible.value = true
}

// Handle modal save – merge data back into main form
const handleProviderSaved = (data: any) => {
    const key = currentProvider.value?.key
    if (!key) return

    // Update main form
    form[`${key}_enabled`] = true
    Object.keys(data).forEach(k => {
        form[`${key}_${k}`] = data[k]
    })

    // Update display list
    const listItem = providerList.value.find(p => p.key === key)
    if (listItem) {
        listItem.enabled = true
        Object.assign(listItem, data)
    }

    isDirty.value = true
    modalVisible.value = false
}

// Get status badge
const getStatus = (provider: Provider) => {
    if (!provider.enabled) return { label: 'Not Connected', severity: 'secondary' }
    const hasCredentials = provider.client_id && provider.client_secret
    return hasCredentials
        ? { label: 'Connected', severity: 'success' }
        : { label: 'Incomplete', severity: 'warning' }
}

const submit = () => {
    form.post(route('settings.website.social.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isDirty.value = false
        },
    })
}
</script>

<template>
    <AuthenticatedLayout title="Social Authentication" :crumb="props.crumbs">

        <Head title="Social Authentication" />

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
                                <h1 class="text-2xl font-bold text-gray-900">Social Authentication</h1>
                                <p class="text-gray-600 mt-1">Enable social login options for parents, students and
                                    staff</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing"
                                    :disabled="!isDirty || form.processing" />
                            </div>
                        </div>

                        <!-- Provider Cards Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <Card v-for="provider in providerList" :key="provider.key"
                                class="cursor-pointer hover:shadow-lg transition-shadow">
                                <template #content>
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center gap-4">
                                            <i :class="provider.icon"></i>
                                            <div>
                                                <h3 class="font-semibold text-lg">{{ provider.name }}</h3>
                                                <p class="text-sm text-gray-600">
                                                    {{ provider.name === 'Sign in with Apple' ? 'Secure sign-in with Apple ID' : `Connect with ${provider.name}` }}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge :value="getStatus(provider).label"
                                            :severity="getStatus(provider).severity" />
                                    </div>

                                    <div class="flex items-center justify-between mt-6">
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="provider.enabled"
                                                @change="provider.enabled ? openProviderModal(provider) : form[`${provider.key}_enabled`] = false; isDirty = true" />
                                            <span class="text-sm font-medium">Enable</span>
                                        </div>
                                        <Button label="View Integration" severity="secondary" outlined size="small"
                                            @click="openProviderModal(provider)" />
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>

        <!-- Modal for Provider Credentials -->
        <SocialProviderModal v-if="modalVisible && currentProvider" :visible="modalVisible" :provider="currentProvider"
            :redirect-uris="props.redirect_uris" @close="modalVisible = false" @saved="handleProviderSaved" />
    </AuthenticatedLayout>
</template>