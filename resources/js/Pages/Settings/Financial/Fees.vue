<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import SettingsRow from '../Partials/SettingsRow.vue';
import { Button, Textarea, ToggleSwitch } from 'primevue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    settings: {
        allow_offline_payments: boolean,
        allow_offline_payments_description: string,
        offline_payment_instructions: string,
        lock_student_panel: boolean,
        print_receipt: boolean,
        receipt_as_single_page: boolean,
    }
}>()

const form = useForm({
    allow_offline_payments: props.settings.allow_offline_payments,
    allow_offline_payments_description: props.settings.allow_offline_payments_description,
    offline_payment_instructions: props.settings.offline_payment_instructions,
    lock_student_panel: props.settings.lock_student_panel,
    print_receipt: props.settings.print_receipt,
    receipt_as_single_page: props.settings.receipt_as_single_page,
})
</script>

<template>
    <Head title="Fees Settings" />
    <AuthenticatedLayout title="Fees" :crumb="[{ label: 'Settings' }, { label: 'Finance' }, { label: 'Fees' }]">
        <SettingsLayout>
            <template #main>
                <div class="mx-4">
                    <form action="" method="post">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Fees Settings</h5>
                                <p>Fees Settings Configuration</p>
                            </div>
                            <div class="mb-3">
                                <Button class="" label="Save" :loading="form.processing" type="submit">Save</Button>
                            </div>
                        </div>
                        <div class="block space-y-4">
                            <SettingsRow label="Allow Offline Payments" description="">
                                <ToggleSwitch v-model="form.allow_offline_payments" />
                            </SettingsRow>

                            <SettingsRow label="Instructions For Offline Bank Payments" description="Instructions For How Offline Payments To Bank Are To Be Made">
                                <Textarea rows="3" fluid v-model="form.offline_payment_instructions" />
                            </SettingsRow>

                            <SettingsRow label="Lock Student Panel" description="If a student's is to be disabled if they defailt in payment">
                                <ToggleSwitch v-model="form.lock_student_panel" />
                            </SettingsRow>

                            <SettingsRow label="Print Receipt" description="If a copy of the receipt is to be made for printing">
                                <ToggleSwitch  v-model="form.print_receipt"/>
                            </SettingsRow>

                            <SettingsRow label="Receipt As Single Page" description="Force receipt to fit in one page">
                                <ToggleSwitch v-model="form.receipt_as_single_page" />
                            </SettingsRow>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
