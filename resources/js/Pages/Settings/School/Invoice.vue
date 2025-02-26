<script setup lang="ts">
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Avatar, Button, Card, FileUpload, InputGroup, InputGroupAddon, InputText, Select, Textarea, useToast, ToggleSwitch } from 'primevue';
import SettingsRow from '../Partials/SettingsRow.vue';
import { Head, useForm } from '@inertiajs/vue3';

const Toast = useToast();

const props = defineProps<{
    settings: {
        invoice_logo: string,
        invoice_prefix: string,
        invoice_due: string,
        invoice_round_off: string,
        show_company_details: boolean,
        invoice_header_terms: string,
        invoice_footer_terms: string,
    }
}>();

const form = useForm({
    invoice_logo: props.settings.invoice_logo ?? 'assets/img/logo-small.svg',
    invoice_prefix: props.settings.invoice_prefix ?? 'INV',
    invoice_due: props.settings.invoice_due ?? '30',
    invoice_round_off: props.settings.invoice_round_off ?? 'Round-Off',
    show_company_details: props.settings.show_company_details ?? true,
    invoice_header_terms: props.settings.invoice_header_terms ?? '',
    invoice_footer_terms: props.settings.invoice_footer_terms ?? '',
});

const submitted = () => {
    form.post(route('website.invoice.post'), {
        onSuccess() {
            Toast.add({severity: 'success', summary: 'Success', detail: 'Invoice Setting updated successfully.', life: 3000});
        },
        onError(){
            Toast.add({severity: 'error', summary: 'Error', detail: 'Invoice Setting not updated.', life: 3000});
        }
    })
}
</script>

<template>
    <Head title="Invoice Setting" />
    <AuthenticatedLayout title="Invoice Setting" :crumb="[{label:'Setting'},{label:'Website'},{label:'Invoice Setting'}]"
     :buttons="[{icon: 'ti ti-refresh', variant: 'outlined', severity: 'contrast', size:'small'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <form :action="route('website.invoice.post')" @submit.prevent="submitted" class="space-y-4 space-x-3">
                    <div class="flex items-center justify-between flex-wrap border-b px-3 pt-3">
                        <div class="mb-3 text-color">
                            <h5 class="mb-1">Invoice Settings</h5>
                            <p>Collection of settings for Invoice</p>
                        </div>
                        <div class="mb-3 space-x-3">
                            <Button severity="secondary" type="button">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Save</Button>
                        </div>
                    </div>
                    <div class="">
                        <Card>
                            <template #title>
                                <h6>Invoice Logo</h6>
                                <p>Upload logo of you company to display in Invoice</p>
                            </template>
                            <template #content>
                                <div class="flex justify-between mb-3">
                                    <div class="flex items-center gap-x-3">
                                        <Avatar :image="form.invoice_logo" size="xlarge" />
                                        <h5>Logo</h5>
                                    </div>
                                </div>
                                <FileUpload v-model="form.invoice_logo" mode="advanced">
                                    <template #empty>
                                    <div class="bg-transparent mr-0 border-0 text-center">
                                        <p class="text-xs mb-2"><span class="text-primary">Click to
                                                Upload</span> or drag and drop
                                        </p>
                                        <h6>JPG or PNG</h6>
                                        <h6>(Max 450 x 450 px)</h6>
                                    </div>
                                    </template>
                                </FileUpload>
                            </template>
                        </Card>
                    </div>

                    <SettingsRow label="Invoice Prefix" description="Add prefix to your Invoice">
                        <InputText v-model="form.invoice_prefix" fluid />
                    </SettingsRow>

                    <SettingsRow label="Invoice Due" description="Select due date to display in Invoice">
                        <InputGroup>
                            <InputText v-model="form.invoice_due" type="number" fluid />
                            <InputGroupAddon>
                                Days
                            </InputGroupAddon>
                        </InputGroup>
                    </SettingsRow>

                    <SettingsRow label="Invoice Round Off" description="Value round off in invoice">
                        <Select v-model="form.invoice_round_off" :options="['Round-Off', 'Round-Up']" fluid />
                    </SettingsRow>

                    <SettingsRow label="Show Company Details" description="Show/hide company details in invoice">
                        <ToggleSwitch v-model="form.show_company_details" />
                    </SettingsRow>

                    <SettingsRow label="Invoice Header Terms" description="Header Terms">
                        <Textarea v-model="form.invoice_header_terms" fluid />
                    </SettingsRow>

                    <SettingsRow label="Invoice Footer Terms" description="Footer Terms">
                        <Textarea v-model="form.invoice_footer_terms" fluid />
                    </SettingsRow>
                </form>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
