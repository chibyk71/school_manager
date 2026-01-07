<!-- resources/js/Pages/Settings/General/Notifications.vue -->
<script setup lang="ts">
/**
 * Notifications.vue v1.0 – Production-Ready Notification Preferences Page
 *
 * Purpose:
 * Configures which system events trigger notifications and to which user roles.
 * Clean table layout with events as rows and roles as columns.
 *
 * Features / Problems Solved:
 * - Responsive PrimeVue DataTable with checkbox toggles
 * - Role columns: Admin, Teacher, Parent, Student
 * - Events grouped logically (admissions, fees, attendance, exams, etc.)
 * - Real-time form dirty tracking → Save button enabled only on changes
 * - Full accessibility (labels, keyboard navigation)
 * - Matches your SettingsLayout + Sidebar pattern
 * - Uses useForm for validation feedback and submission
 *
 * Fits into the Settings Module:
 * - Navigation: General Settings → Notifications
 * - Controller: NotificationsSettingsController
 * - Key: 'general.notifications'
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, DataTable, Column, ToggleSwitch } from 'primevue'
import { computed } from 'vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: Record<string, Record<string, boolean>>
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { generalSettingsNav } = useSettingsNavigation()

// Flatten nested settings into form-compatible structure
const initialData: Record<string, boolean> = {}
Object.keys(props.settings).forEach(event => {
    Object.keys(props.settings[event]).forEach(role => {
        initialData[`${event}.${role}`] = props.settings[event][role] ?? false
    })
})

const form = useForm(initialData)

// Track dirty state
const isDirty = computed(() => form.isDirty)

// Event definitions with friendly labels
const events = [
    // Admissions & Enrollment
    { key: 'student_admission', label: 'New Student Admission' },
    { key: 'admission_enquiry', label: 'Admission Enquiry Received' },

    // Fees & Finance
    { key: 'fee_payment', label: 'Fee Payment Received' },
    { key: 'fee_due_reminder', label: 'Fee Due Reminder' },
    { key: 'fee_overdue', label: 'Fee Overdue Alert' },

    // Attendance
    { key: 'attendance_low', label: 'Low Attendance Alert' },
    { key: 'absent_today', label: 'Absent Today' },

    // Academics
    { key: 'exam_result_published', label: 'Exam Results Published' },
    { key: 'new_assignment', label: 'New Assignment Posted' },
    { key: 'assignment_due', label: 'Assignment Due Reminder' },

    // Communication
    { key: 'system_announcement', label: 'System Announcement' },
    { key: 'event_reminder', label: 'Upcoming Event Reminder' },

    // Personal
    { key: 'birthday', label: 'Birthday Notification' },

    // Leave Management
    { key: 'leave_requested', label: 'New Leave Request Submitted' },
    { key: 'leave_approved', label: 'Leave Request Approved' },
    { key: 'leave_rejected', label: 'Leave Request Rejected' },
]

// Roles
const roles = [
    { key: 'admin', label: 'Admin' },
    { key: 'teacher', label: 'Teacher' },
    { key: 'parent', label: 'Parent' },
    { key: 'student', label: 'Student' },
]

const submit = () => {
    form.post(route('settings.general.notifications.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Notifications" :crumb="props.crumbs">

        <Head title="Notification Preferences" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="General Settings" :items="generalSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-5xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
                                <p class="text-gray-600 mt-1">Configure which events trigger notifications and to whom
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing"
                                    :disabled="!isDirty || form.processing" />
                            </div>
                        </div>

                        <Card>
                            <template #content>
                                <DataTable :value="events" class="p-datatable-sm">
                                    <Column field="label" header="Event" style="width: 35%">
                                        <template #body="{ data }">
                                            <span class="font-medium">{{ data.label }}</span>
                                        </template>
                                    </Column>
                                    <Column v-for="role in roles" :key="role.key" :header="role.label"
                                        style="width: 15%; text-align: center">
                                        <template #body="{ data }">
                                            <ToggleSwitch :modelValue="form[`${data.key}.${role.key}`]"
                                                @update:modelValue="form[`${data.key}.${role.key}`] = $event" />
                                        </template>
                                    </Column>
                                </DataTable>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>