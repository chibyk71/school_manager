<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, useToast } from 'primevue';
import EmailCard from './EmailCard.vue';
import { useForm } from '@inertiajs/vue3';

    const emailSettings = defineProps<{
        phpMailer: boolean;
        smtp: boolean;
        sendGrid: boolean;
        mailGun: boolean;
        amazonSES: boolean;
    }>();

    const toast = useToast();

    const form = useForm(emailSettings);

    const saveEmailSettings = () => {
        form.post(route('system.email.post'), {
            preserveScroll: true,
            onSuccess: () => {
                // Show success message
                toast.add({ severity: 'success', summary: 'Success', detail: 'Email settings saved successfully.' });
            },
            onError: () => {
                // Show error message
                toast.add({ severity: 'error', summary: 'Error', detail: 'Email settings not saved.' });
            },
        });
    };
</script>

<template>
    <AuthenticatedLayout title="Email Setting" :crumb="[{label:'Settings'},{label:'System'},{label:'Email'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <form @submit.prevent="saveEmailSettings" :action="route('system.email.post')" class="space-y-4 space-x-3 mx-4">
                    <div class="flex items-center justify-between flex-wrap border-b pt-3">
                        <div class="mb-3">
                            <h5 class="mb-1">Email Settings</h5>
                            <p>Email Settings Configuration</p>
                        </div>
                        <div class="mb-3">
                            <Button class="" type="submit">Save</Button>
                        </div>
                    </div>
                    <div class="grid xxl:grid-cols-3 xl:grid-cols-2 gap-4">
                        <EmailCard v-model="form.phpMailer" title="PHP Mailer" content="Used to send emails safely and easily via PHP code from a web server." image="assets/img/icons/php-icon.svg" :status="true" />

                        <EmailCard v-model="form.smtp" title="SMTP" content="SMTP is used to send, relay or forward messages from a mail client." image="assets/img/icons/smtp-icon.svg" :status="false" />
                       
                        <EmailCard v-model="form.sendGrid" title="SendGrid" content="Cloud-based email marketing tool that assists marketers and developers." image="assets/img/icons/sendgrid-icon.svg" :status="false" />

                        <EmailCard v-model="form.mailGun" title="Mailgun" content="Mailgun is a set of powerful APIs that enable you to send, receive and track email effortlessly." image="assets/img/icons/mailgun-icon.svg" :status="false" />

                        <EmailCard v-model="form.amazonSES" title="Amazon SES" content="Amazon Simple Email Service (Amazon SES) is a cloud-based email sending service designed to help digital marketers and application developers send marketing, notification, and transactional emails." image="assets/img/icons/amazon-ses-icon.svg" :status="false" />
                    </div>
                </form>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
