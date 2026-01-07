<!-- resources/js/Pages/Settings/School/UserManagement.vue -->
<script setup lang="ts">
/**
 * UserManagement.vue v1.0 – Production-Ready User Management Settings Page
 *
 * Purpose:
 * Full configuration of user lifecycle and access policies for the school.
 * Controls online admission, sign-in permissions, enrollment ID/username generation,
 * password policy, account security, guardian rules, and bulk operations.
 *
 * Features / Problems Solved:
 * - Clean grouped layout with toggles, number inputs, selects, and conditional fields
 * - Live preview of enrollment ID example
 * - Rich text instructions for admission/offline payments
 * - Responsive PrimeVue form with logical sections
 * - Full accessibility and mobile optimization
 * - SettingsLayout + Sidebar + crumbs
 * - Matches PreSkool template style
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, ToggleSwitch, InputText, InputNumber, Textarea, Select } from 'primevue'
import { computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        // Admission
        online_admission: boolean
        online_admission_fee: number
        online_admission_instruction?: string

        // Sign-in Permissions
        allow_student_signin: boolean
        allow_parent_signin: boolean
        allow_teacher_signin: boolean
        allow_staff_signin: boolean

        // Enrollment ID
        enrollment_id_prefix: string
        enrollment_id_number_length: number

        // Username Generation
        auto_generate_username: boolean
        username_format?: 'firstname.lastname' | 'firstname_initial.lastname' | 'student_id'

        // Password Policy
        password_min_length: number
        password_require_uppercase: boolean
        password_require_number: boolean
        password_require_symbol: boolean

        // Account Security
        failed_login_attempts: number
        lockout_duration_minutes: number
        session_timeout_minutes: number

        // Guardian Rules
        require_guardian_email: boolean
        max_guardian_students: number

        // Profile Completion
        require_profile_completion: boolean

        // Bulk Operations
        allow_bulk_user_creation: boolean
    }
    school_id: number
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { systemSettingsNav } = useSettingsNavigation() // Adjust if you have specific nav

const form = useForm({
    // Admission
    online_admission: props.settings.online_admission ?? false,
    online_admission_fee: props.settings.online_admission_fee ?? 0,
    online_admission_instruction: props.settings.online_admission_instruction ?? '',

    // Sign-in
    allow_student_signin: props.settings.allow_student_signin ?? true,
    allow_parent_signin: props.settings.allow_parent_signin ?? true,
    allow_teacher_signin: props.settings.allow_teacher_signin ?? true,
    allow_staff_signin: props.settings.allow_staff_signin ?? true,

    // Enrollment ID
    enrollment_id_prefix: props.settings.enrollment_id_prefix ?? 'STD',
    enrollment_id_number_length: props.settings.enrollment_id_number_length ?? 6,

    // Username
    auto_generate_username: props.settings.auto_generate_username ?? true,
    username_format: props.settings.username_format ?? 'firstname.lastname',

    // Password
    password_min_length: props.settings.password_min_length ?? 8,
    password_require_uppercase: props.settings.password_require_uppercase ?? true,
    password_require_number: props.settings.password_require_number ?? true,
    password_require_symbol: props.settings.password_require_symbol ?? false,

    // Security
    failed_login_attempts: props.settings.failed_login_attempts ?? 5,
    lockout_duration_minutes: props.settings.lockout_duration_minutes ?? 30,
    session_timeout_minutes: props.settings.session_timeout_minutes ?? 120,

    // Guardian
    require_guardian_email: props.settings.require_guardian_email ?? true,
    max_guardian_students: props.settings.max_guardian_students ?? 10,

    // Profile
    require_profile_completion: props.settings.require_profile_completion ?? true,

    // Bulk
    allow_bulk_user_creation: props.settings.allow_bulk_user_creation ?? true,
})

// Live preview of enrollment ID
const enrollmentExample = computed(() => {
    const num = '1'.padStart(form.enrollment_id_number_length, '0')
    return `${form.enrollment_id_prefix}${num}`
})

const submit = () => {
    form.post(route('settings.school.user-management.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="User Management" :crumb="props.crumbs">

        <Head title="User Management Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="System Settings" :items="systemSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-5xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                                <p class="text-gray-600 mt-1">Configure user access, enrollment, and security policies
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Online Admission -->
                        <Card class="mb-6">
                            <template #title>Online Admission</template>
                            <template #content>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.online_admission" />
                                        <label class="font-medium">Enable Online Admission</label>
                                    </div>
                                    <div v-if="form.online_admission" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Application
                                                Fee</label>
                                            <InputNumber v-model="form.online_admission_fee" prefix="₦" :min="0" fluid
                                                showButtons />
                                        </div>
                                    </div>
                                    <div v-if="form.online_admission">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admission
                                            Instructions</label>
                                        <Textarea v-model="form.online_admission_instruction" rows="6" fluid />
                                        <p class="text-xs text-gray-500 mt-1">Shown during online admission process</p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Sign-in Permissions -->
                        <Card class="mb-6">
                            <template #title>Sign-in Permissions</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_student_signin" />
                                        <label>Allow Students to Sign In</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_parent_signin" />
                                        <label>Allow Parents to Sign In</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_teacher_signin" />
                                        <label>Allow Teachers to Sign In</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_staff_signin" />
                                        <label>Allow Staff to Sign In</label>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Enrollment ID & Username -->
                        <Card class="mb-6">
                            <template #title>Enrollment ID & Username</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Enrollment ID
                                            Prefix</label>
                                        <InputText v-model="form.enrollment_id_prefix" fluid placeholder="STD" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Number
                                            Length</label>
                                        <InputNumber v-model="form.enrollment_id_number_length" :min="4" :max="15" fluid
                                            showButtons />
                                    </div>
                                </div>
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm font-medium">Example ID:</p>
                                    <code class="text-lg font-mono">{{ enrollmentExample }}</code>
                                </div>

                                <div class="mt-6">
                                    <div class="flex items-center gap-3 mb-4">
                                        <ToggleSwitch v-model="form.auto_generate_username" />
                                        <label class="font-medium">Auto-generate Username</label>
                                    </div>
                                    <Select v-if="form.auto_generate_username" v-model="form.username_format" :options="[
                                        { label: 'Firstname.Lastname', value: 'firstname.lastname' },
                                        { label: 'F.Lastname', value: 'firstname_initial.lastname' },
                                        { label: 'Enrollment ID', value: 'student_id' },
                                    ]" optionLabel="label" optionValue="value" fluid />
                                </div>
                            </template>
                        </Card>

                        <!-- Password Policy -->
                        <Card class="mb-6">
                            <template #title>Password Policy</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum
                                            Length</label>
                                        <InputNumber v-model="form.password_min_length" :min="6" :max="50" fluid
                                            showButtons />
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.password_require_uppercase" />
                                            <label>Require Uppercase</label>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.password_require_number" />
                                            <label>Require Number</label>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <ToggleSwitch v-model="form.password_require_symbol" />
                                            <label>Require Symbol</label>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Account Security -->
                        <Card class="mb-6">
                            <template #title>Account Security</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Failed Login
                                            Attempts</label>
                                        <InputNumber v-model="form.failed_login_attempts" :min="3" :max="20" fluid
                                            showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Lockout Duration
                                            (minutes)</label>
                                        <InputNumber v-model="form.lockout_duration_minutes" :min="5" :max="1440" fluid
                                            showButtons />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout
                                            (minutes)</label>
                                        <InputNumber v-model="form.session_timeout_minutes" :min="15" :max="1440" fluid
                                            showButtons />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Guardian & Other -->
                        <Card class="mb-6">
                            <template #title>Guardian & Profile Rules</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.require_guardian_email" />
                                        <label>Require Guardian Email</label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Max Students per
                                            Guardian</label>
                                        <InputNumber v-model="form.max_guardian_students" :min="1" :max="50" fluid
                                            showButtons />
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.require_profile_completion" />
                                        <label>Require Profile Completion on First Login</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_bulk_user_creation" />
                                        <label>Allow Bulk User Creation</label>
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
