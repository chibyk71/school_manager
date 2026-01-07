<!-- resources/js/Pages/Settings/Financial/Fees.vue -->
<script setup lang="ts">
/**
 * Fees.vue v1.0 – Production-Ready Fees Settings Page
 *
 * Purpose:
 * Full configuration of fee policies: offline payments, panel lock, receipts, late penalties.
 *
 * Features / Problems Solved:
 * - Clean grouped layout with toggles and conditional fields
 * - Rich text instructions for offline payments
 * - Late payment penalty with type, amount, frequency, grace period
 * - Responsive PrimeVue form
 * - Full accessibility
 * - SettingsLayout + Sidebar + crumbs
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue'
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button, Card, ToggleSwitch, InputText, Textarea, InputNumber, Select } from 'primevue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'

interface Props {
    settings: {
        allow_offline_payments: boolean
        offline_payment_instructions?: string
        lock_student_panel_on_default: boolean
        print_receipt_after_payment: boolean
        receipt_single_page: boolean
        late_payment_penalty: {
            enabled: boolean
            type?: 'percentage' | 'fixed'
            amount?: number
            apply_per?: 'day' | 'once'
            grace_period_days?: number
        }
    }
    crumbs: Array<{ label: string }>
}

const props = defineProps<Props>()
const { financialSettingsNav } = useSettingsNavigation()

const form = useForm({
    allow_offline_payments: props.settings.allow_offline_payments ?? true,
    offline_payment_instructions: props.settings.offline_payment_instructions ?? '',
    lock_student_panel_on_default: props.settings.lock_student_panel_on_default ?? false,
    print_receipt_after_payment: props.settings.print_receipt_after_payment ?? true,
    receipt_single_page: props.settings.receipt_single_page ?? false,
    late_payment_penalty: {
        enabled: props.settings.late_payment_penalty?.enabled ?? false,
        type: props.settings.late_payment_penalty?.type ?? 'percentage',
        amount: props.settings.late_payment_penalty?.amount ?? 0,
        apply_per: props.settings.late_payment_penalty?.apply_per ?? 'day',
        grace_period_days: props.settings.late_payment_penalty?.grace_period_days ?? 0,
    },
})

const submit = () => {
    form.post(route('settings.financial.fees.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout title="Fees Settings" :crumb="props.crumbs">

        <Head title="Fees Settings" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Financial" :items="financialSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-4xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Fees Settings</h1>
                                <p class="text-gray-600 mt-1">Configure fee payment policies and behavior</p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <!-- Offline Payments -->
                        <Card class="mb-6">
                            <template #title>Offline Payments</template>
                            <template #content>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.allow_offline_payments" />
                                        <label class="font-medium">Allow Offline Bank Transfers</label>
                                    </div>
                                    <div v-if="form.allow_offline_payments">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment
                                            Instructions</label>
                                        <Textarea v-model="form.offline_payment_instructions" rows="6" fluid />
                                        <p class="text-xs text-gray-500 mt-1">Shown on invoices and parent portal</p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Student Panel -->
                        <Card class="mb-6">
                            <template #title>Student Access</template>
                            <template #content>
                                <div class="flex items-center gap-3">
                                    <ToggleSwitch v-model="form.lock_student_panel_on_default" />
                                    <label class="font-medium">Lock Student Panel on Payment Default</label>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">Disables access if fees are overdue</p>
                            </template>
                        </Card>

                        <!-- Receipt Options -->
                        <Card class="mb-6">
                            <template #title>Receipt Printing</template>
                            <template #content>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.print_receipt_after_payment" />
                                        <label class="font-medium">Auto-Print Receipt After Payment</label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.receipt_single_page" />
                                        <label class="font-medium">Force Receipt to Single Page</label>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Late Payment Penalty -->
                        <Card>
                            <template #title>Late Payment Penalty</template>
                            <template #content>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <ToggleSwitch v-model="form.late_payment_penalty.enabled" />
                                        <label class="font-medium">Enable Late Payment Penalty</label>
                                    </div>

                                    <div v-if="form.late_payment_penalty.enabled"
                                        class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Penalty
                                                Type</label>
                                            <Select v-model="form.late_payment_penalty.type"
                                                :options="[{ label: 'Percentage', value: 'percentage' }, { label: 'Fixed Amount', value: 'fixed' }]"
                                                fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                                            <InputNumber v-model="form.late_payment_penalty.amount" :min="0" fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Apply
                                                Penalty</label>
                                            <Select v-model="form.late_payment_penalty.apply_per"
                                                :options="[{ label: 'Per Day', value: 'day' }, { label: 'Once Only', value: 'once' }]"
                                                fluid />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Grace Period
                                                (days)</label>
                                            <InputNumber v-model="form.late_payment_penalty.grace_period_days" :min="0"
                                                :max="365" fluid />
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
