<!-- resources/js/Pages/Settings/Academic/AttendanceRules.vue -->
<script setup lang="ts">
/**
 * AttendanceRules.vue v1.0 – Production-Ready Attendance Policy Configuration Page
 *
 * Purpose:
 * Full customization of attendance rules and thresholds.
 *
 * Features / Problems Solved:
 * - Clean grouped layout with toggles and number inputs
 * - Clear descriptions for each policy
 * - Responsive PrimeVue form
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, ToggleSwitch, InputNumber } from 'primevue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        minimum_percentage: number
        count_late_as_half_day: boolean
        late_grace_minutes: number
        absent_after_minutes: number
        notify_parent_at_percentage: number
        mark_weekends_as_holiday: boolean
        require_reason_for_absence: boolean
    }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { academicSettingsNav } = useSettingsNavigation()

const form = useForm({
    minimum_percentage: props.settings.minimum_percentage ?? 75,
    count_late_as_half_day: props.settings.count_late_as_half_day ?? true,
    late_grace_minutes: props.settings.late_grace_minutes ?? 15,
    absent_after_minutes: props.settings.absent_after_minutes ?? 120,
    notify_parent_at_percentage: props.settings.notify_parent_at_percentage ?? 85,
    mark_weekends_as_holiday: props.settings.mark_weekends_as_holiday ?? true,
    require_reason_for_absence: props.settings.require_reason_for_absence ?? true,
})

const submit = () => {
    form.post(route('settings.academic.attendance.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Attendance Rules" :crumb="props.crumbs">

        <Head title="Attendance Rules" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Academic" :items="academicSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Attendance Rules</h1>
                                <p class="text-gray-600 mt-1">Define attendance policies and thresholds</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Minimum Attendance -->
                        <Card class="mb-6">
                            <template #title>Minimum Attendance Requirement</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Minimum Percentage for Promotion/Exams
                                        </label>
                                        <InputNumber v-model="form.minimum_percentage" suffix="%" :min="50" :max="100"
                                            fluid showButtons />
                                        <p class="text-xs text-gray-500 mt-1">Students below this will be flagged</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Notify Parents When Below
                                        </label>
                                        <InputNumber v-model="form.notify_parent_at_percentage" suffix="%" :min="50"
                                            :max="100" fluid showButtons />
                                        <p class="text-xs text-gray-500 mt-1">Triggers low attendance alert</p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Late & Absent Rules -->
                        <Card class="mb-6">
                            <template #title>Late Arrival & Absence Rules</template>
                            <template #content>
                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Late Grace
                                                Period (minutes)</label>
                                            <InputNumber v-model="form.late_grace_minutes" :min="0" :max="120" fluid
                                                showButtons />
                                            <p class="text-xs text-gray-500 mt-1">Arrival after this is marked late</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Marked Absent
                                                After (minutes)</label>
                                            <InputNumber v-model="form.absent_after_minutes" :min="30" :max="480" fluid
                                                showButtons />
                                            <p class="text-xs text-gray-500 mt-1">Late beyond this counts as absent</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.count_late_as_half_day" />
                                        <label class="font-medium">Count excessive late arrivals as half day</label>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- General Policies -->
                        <Card>
                            <template #title>General Policies</template>
                            <template #content>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.mark_weekends_as_holiday" />
                                        <label class="font-medium">Weekends are automatically holidays</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.require_reason_for_absence" />
                                        <label class="font-medium">Require reason for absence/late</label>
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