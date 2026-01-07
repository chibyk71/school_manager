<!-- resources/js/Pages/Settings/Website/Prefixes.vue -->
<script setup lang="ts">
/**
 * Prefixes.vue v1.0 – Production-Ready ID Prefixes Settings Page
 *
 * Purpose:
 * Allows admins to customize prefixes for all auto-generated identifiers in the system.
 * Used in student IDs (STU-2024-001), invoices (INV-2024001), etc.
 *
 * Features / Problems Solved:
 * - Clean, grouped layout with clear labels and descriptions
 * - Responsive PrimeVue grid (2–3 columns on desktop)
 * - Full integration with SettingsLayout + SettingsSidebar
 * - Proper crumb trail for AuthenticatedLayout
 * - Loading state during save
 * - Validation errors displayed per field
 * - Accessible: labels, proper form structure
 * - Uses useForm for type-safety and Inertia integration
 *
 * Fits into the Settings Module:
 * - Navigation: Website & Branding → Prefixes
 * - Submits to PrefixesSettingsController@store
 * - Key: 'website.prefixes'
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, InputText } from 'primevue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        student_id: string
        staff_id: string
        parent_id: string
        invoice: string
        payment: string
        receipt: string
        class: string
        section: string
        subject: string
        exam: string
        fee_type: string
        transport_route: string
        library_book: string
    }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { websiteSettingsNav } = useSettingsNavigation()

const form = useForm({
    student_id: props.settings.student_id ?? 'STU',
    staff_id: props.settings.staff_id ?? 'STF',
    parent_id: props.settings.parent_id ?? 'PAR',
    invoice: props.settings.invoice ?? 'INV',
    payment: props.settings.payment ?? 'PAY',
    receipt: props.settings.receipt ?? 'REC',
    class: props.settings.class ?? 'CLS',
    section: props.settings.section ?? 'SEC',
    subject: props.settings.subject ?? 'SUB',
    exam: props.settings.exam ?? 'EXM',
    fee_type: props.settings.fee_type ?? 'FEE',
    transport_route: props.settings.transport_route ?? 'TR',
    library_book: props.settings.library_book ?? 'LIB',
})

const submit = () => {
    form.post(route('settings.website.prefixes.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Prefixes" :crumb="props.crumbs">

        <Head title="Prefixes Settings" />

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
                                <h1 class="text-2xl font-bold text-gray-900">Prefixes</h1>
                                <p class="text-gray-600 mt-1">Customize prefixes for auto-generated IDs (e.g.,
                                    STU-2024-001)</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Student & Staff -->
                        <Card class="mb-6">
                            <template #title>People</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Student ID</label>
                                        <InputText v-model="form.student_id" fluid placeholder="e.g., STU" />
                                        <p class="text-xs text-gray-500 mt-1">Used for student numbers</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Staff ID</label>
                                        <InputText v-model="form.staff_id" fluid placeholder="e.g., STF" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Parent ID</label>
                                        <InputText v-model="form.parent_id" fluid placeholder="e.g., PAR" />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Finance -->
                        <Card class="mb-6">
                            <template #title>Finance</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice</label>
                                        <InputText v-model="form.invoice" fluid placeholder="e.g., INV" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment</label>
                                        <InputText v-model="form.payment" fluid placeholder="e.g., PAY" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Receipt</label>
                                        <InputText v-model="form.receipt" fluid placeholder="e.g., REC" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Fee Type</label>
                                        <InputText v-model="form.fee_type" fluid placeholder="e.g., FEE" />
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Academic & Operations -->
                        <Card class="mb-6">
                            <template #title>Academic & Operations</template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                                        <InputText v-model="form.class" fluid placeholder="e.g., CLS" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                                        <InputText v-model="form.section" fluid placeholder="e.g., SEC" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                        <InputText v-model="form.subject" fluid placeholder="e.g., SUB" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Exam</label>
                                        <InputText v-model="form.exam" fluid placeholder="e.g., EXM" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Transport
                                            Route</label>
                                        <InputText v-model="form.transport_route" fluid placeholder="e.g., TR" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Library Book</label>
                                        <InputText v-model="form.library_book" fluid placeholder="e.g., LIB" />
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