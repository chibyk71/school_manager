<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Message } from 'primevue';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <Head title="Email Verification" />
    <GuestLayout>
        <Message severity="success" v-if="verificationLinkSent">
            A new verification link has been sent to the email address you
            provided during registration.
        </Message>
        <Card>
            <template #content>
                <div class=" mb-4">
                    <h2 class="mb-2 text-2xl/none font-bold text-color">Verify Email?</h2>
                    <p class="mb-0 text-sm/none text-color">
                        Thanks for signing up! Before getting started, could you verify your
                        email address by clicking on the link we just emailed to you? If you
                        didn't receive the email, we will gladly send you another.</p>
                </div>
            </template>

            <form @submit.prevent="submit">
                <div class="mt-4 flex items-center flex-col gap-y-3">
                    <Button type="submit" fluid :disabled="form.processing">
                        Resend Verification Email
                    </Button>

                    <Button :as="Link" :href="route('logout')" severity="secondary" fluid method="post">
                        Log Out
                    </Button>
                </div>
            </form>
        </Card>
    </GuestLayout>
</template>
