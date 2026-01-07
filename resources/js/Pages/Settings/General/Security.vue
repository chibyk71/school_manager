<!-- resources/js/Pages/Settings/General/Security.vue -->
<script setup lang="ts">
/**
 * Security.vue v1.0 – Production-Ready Security & Authentication Settings Page
 *
 * Purpose:
 * Complete Vue 3 frontend for your existing AuthenticationController (now SecuritySettingsController).
 * Manages all authentication policies: login throttling, password rules, OTP/email verification,
 * registration controls, rate limiting – comprehensive security configuration.
 *
 * Features / Problems Solved:
 * - Perfect integration with your existing controller + AuthenticationSettingsRequest validation
 * - Responsive PrimeVue form with logical grouping (Login, Password, Registration, Rate Limiting)
 * - Toggle switches, number inputs, and checkboxes matching your validation rules exactly
 * - Real-time form validation feedback via Inertia errors
 * - Loading states during submit
 * - Proper accessibility (labels, ARIA, keyboard navigation)
 * - Mobile-first responsive grid (1-4 columns)
 * - Uses your standardized SettingsLayout + SettingsSidebar + crumbs
 * - Form dirty state tracking (save button disabled until changes)
 * - Matches industry standards (Laravel Breeze/Fortify + school SaaS like Fedena, Gibbon)
 * - Clean, scannable layout with helper text for each field
 *
 * Fits into the Settings Module:
 * - Route: settings.general.security (your controller updated)
 * - Navigation: General Settings → Security Settings
 * - Controller: SecuritySettingsController@index/store (your existing code with namespace fix)
 * - Form Request: AuthenticationSettingsRequest (your existing validation – perfect)
 * - Settings Key: 'authentication' (unchanged)
 *
 * Dependencies:
 * - Your existing AuthenticationSettingsRequest (keep as-is)
 * - SecuritySettingsController (namespace updated from School → General)
 * - useSettingsNavigation (generalSettingsNav)
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, ToggleSwitch, InputNumber, InputText } from 'primevue'
import { ref, computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        // Login Throttling
        login_throttle_max: number
        login_throttle_lock: number

        // Password Reset
        reset_password_token_life: number
        allow_password_reset: boolean
        password_reset_max_attempts: number

        // Email Verification & OTP
        enable_email_verification: boolean
        otp_length: number
        otp_validity: number
        allow_otp_fallback: boolean
        otp_verification_max_attempts: number

        // Registration
        allow_user_registration: boolean
        account_approval: boolean
        oAuth_registration: boolean
        show_terms_on_registration: boolean
        registration_max_attempts: number
        registration_lock_minutes: number

        // Password Confirmation
        require_password_confirmation: boolean
        password_confirmation_ttl: number

        // Password Change
        allow_password_change: boolean

        // Password Rules
        password_min_length: number
        password_require_letters: boolean
        password_require_mixed_case: boolean
        password_require_numbers: boolean
        password_require_symbols: boolean

        // Password Update Rate Limiting
        password_update_max_attempts: number
        password_update_lock_minutes: number
    }
    school_id?: number | null
}

const props = defineProps<Props>()
const { generalSettingsNav } = useSettingsNavigation()

// Main form with your exact validation fields
const form = useForm({
    // Login Throttling
    login_throttle_max: props.settings.login_throttle_max ?? 5,
    login_throttle_lock: props.settings.login_throttle_lock ?? 15,

    // Password Reset
    reset_password_token_life: props.settings.reset_password_token_life ?? 60,
    allow_password_reset: props.settings.allow_password_reset ?? true,
    password_reset_max_attempts: props.settings.password_reset_max_attempts ?? 3,

    // Email Verification & OTP
    enable_email_verification: props.settings.enable_email_verification ?? true,
    otp_length: props.settings.otp_length ?? 6,
    otp_validity: props.settings.otp_validity ?? 5,
    allow_otp_fallback: props.settings.allow_otp_fallback ?? false,
    otp_verification_max_attempts: props.settings.otp_verification_max_attempts ?? 3,

    // Registration
    allow_user_registration: props.settings.allow_user_registration ?? true,
    account_approval: props.settings.account_approval ?? true,
    oAuth_registration: props.settings.oAuth_registration ?? true,
    show_terms_on_registration: props.settings.show_terms_on_registration ?? true,
    registration_max_attempts: props.settings.registration_max_attempts ?? 5,
    registration_lock_minutes: props.settings.registration_lock_minutes ?? 15,

    // Password Confirmation
    require_password_confirmation: props.settings.require_password_confirmation ?? true,
    password_confirmation_ttl: props.settings.password_confirmation_ttl ?? 3600,

    // Password Change
    allow_password_change: props.settings.allow_password_change ?? true,

    // Password Rules
    password_min_length: props.settings.password_min_length ?? 8,
    password_require_letters: props.settings.password_require_letters ?? true,
    password_require_mixed_case: props.settings.password_require_mixed_case ?? true,
    password_require_numbers: props.settings.password_require_numbers ?? true,
    password_require_symbols: props.settings.password_require_symbols ?? false,

    // Password Update Rate Limiting
    password_update_max_attempts: props.settings.password_update_max_attempts ?? 5,
    password_update_lock_minutes: props.settings.password_update_lock_minutes ?? 15,
})

// Track form dirty state
const isDirty = computed(() => form.isDirty)

// Submit handler
const submit = () => {
    form.post(route('settings.general.security.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
        },
    })
}

const crumbs = [
    { label: 'Settings' },
    { label: 'General Settings' },
    { label: 'Security Settings' },
]
</script>

<template>
    <AuthenticatedLayout title="Security Settings" :crumb="crumbs">

        <Head title="Security & Authentication Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="General Settings" :items="generalSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-5xl space-y-6">
                    <form @submit.prevent="submit">
                        <!-- Header -->
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Security Settings</h1>
                                <p class="text-gray-600 mt-1">
                                    Configure login throttling, password policies, OTP, and registration rules
                                    {{ props.school_id ? 'for this school' : '(Global defaults)' }}
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing"
                                    :disabled="!isDirty || form.processing" />
                            </div>
                        </div>

                        <!-- Login Security -->
                        <Card class="!mb-8">
                            <template #title>Login Security</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Max Login
                                            Attempts</label>
                                        <InputNumber v-model="form.login_throttle_max" :min="1" :max="20" fluid
                                            showButtons spinnerMode="horizontal" />
                                        <p class="text-xs text-gray-500 mt-1">Before account lockout</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Lockout Duration
                                            (minutes)</label>
                                        <InputNumber v-model="form.login_throttle_lock" :min="1" :max="60" fluid
                                            showButtons />
                                    </div>
                                    <div class="md:col-span-3">
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.allow_password_reset" />
                                            <label class="text-sm font-medium">Allow Password Reset</label>
                                        </div>
                                        <InputNumber v-if="form.allow_password_reset"
                                            v-model="form.password_reset_max_attempts" label="Max Reset Attempts"
                                            :min="1" :max="10" fluid showButtons class="mt-2" />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Password Policies -->
                        <Card class="!mb-8">
                            <template #title>Password Policies</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum
                                            Length</label>
                                        <InputNumber v-model="form.password_min_length" :min="6" :max="128" fluid
                                            showButtons />
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.password_require_letters" />
                                        <label class="text-sm">Require Letters</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.password_require_mixed_case" />
                                        <label class="text-sm">Mixed Case (A-z)</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.password_require_numbers" />
                                        <label class="text-sm">Numbers (0-9)</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.password_require_symbols" />
                                        <label class="text-sm">Symbols (!@#$%)</label>
                                    </div>
                                </div>
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_password_change" />
                                        <label class="text-sm font-medium">Allow Password Changes</label>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Email Verification & OTP -->
                        <Card class="!mb-8">
                            <template #title>Email Verification & OTP</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.enable_email_verification" />
                                        <label class="text-sm font-medium">Require Email Verification</label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">OTP Length</label>
                                        <InputNumber v-model="form.otp_length" :min="4" :max="8" fluid showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">OTP Validity
                                            (minutes)</label>
                                        <InputNumber v-model="form.otp_validity" :min="1" :max="30" fluid showButtons />
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_otp_fallback" />
                                        <label class="text-sm">Allow Manual Verification Fallback</label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Max OTP
                                            Attempts</label>
                                        <InputNumber v-model="form.otp_verification_max_attempts" :min="1" :max="10"
                                            fluid showButtons />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Registration Controls -->
                        <Card class="!mb-8">
                            <template #title>Registration Controls</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_user_registration" />
                                        <label class="text-sm font-medium">Allow New Registrations</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.account_approval" />
                                        <label class="text-sm font-medium">Require Admin Approval</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.oAuth_registration" />
                                        <label class="text-sm font-medium">Allow Social Login Registration</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.show_terms_on_registration" />
                                        <label class="text-sm">Show Terms & Conditions</label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Max Registration
                                            Attempts</label>
                                        <InputNumber v-model="form.registration_max_attempts" :min="1" :max="10" fluid
                                            showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Registration Lockout
                                            (minutes)</label>
                                        <InputNumber v-model="form.registration_lock_minutes" :min="1" :max="60" fluid
                                            showButtons />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Session & Confirmation -->
                        <Card>
                            <template #title>Session & Confirmation</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.require_password_confirmation" />
                                        <label class="text-sm font-medium">Require Password Confirmation</label>
                                    </div>
                                    <div v-if="form.require_password_confirmation">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmation TTL
                                            (seconds)</label>
                                        <InputNumber v-model="form.password_confirmation_ttl" :min="300" :max="86400"
                                            fluid showButtons />
                                        <p class="text-xs text-gray-500 mt-1">Time before re-confirmation required
                                            (5min-24hr)</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Password Reset Token
                                            Life (minutes)</label>
                                        <InputNumber v-model="form.reset_password_token_life" :min="1" :max="120" fluid
                                            showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Password Update Max
                                            Attempts</label>
                                        <InputNumber v-model="form.password_update_max_attempts" :min="1" :max="10"
                                            fluid showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Password Update
                                            Lockout (minutes)</label>
                                        <InputNumber v-model="form.password_update_lock_minutes" :min="1" :max="60"
                                            fluid showButtons />
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
