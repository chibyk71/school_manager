<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Message, RadioButton, Toast, ProgressSpinner } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

const props = defineProps<{
    status?: string;
    school_id?: number;
    sms_enabled?: boolean;
}>();

const deliveryMethod = ref<'email' | 'sms'>('email');
const showPhoneField = computed(() => deliveryMethod.value === 'sms');

const form = useForm({
    login: '',           // Accepts email OR enrollment_id OR phone
    delivery_method: 'email',
    school_id: props.school_id,
});

const submit = () => {
    form.delivery_method = deliveryMethod.value;

    form.post(route('password.email'), {
        preserveScroll: true,
        onSuccess: () => {
            const method = deliveryMethod.value === 'sms' ? 'SMS' : 'email';
            toast.add({
                severity: 'success',
                summary: 'Sent!',
                detail: `A verification code has been sent to your ${method}.`,
                life: 6000
            });
            form.reset('login');
        },
        onError: (errors) => {
            const msg = errors.login || errors.delivery_method || 'We could not find an account with that information.';
            toast.add({ severity: 'error', summary: 'Not Found', detail: msg, life: 6000 });
        },
        onFinish: () => {
            // Keep delivery method selected
        }
    });
};

// Auto-detect input type for better UX
const inputType = computed(() => {
    if (deliveryMethod.value === 'sms') return 'tel';
    if (form.login.includes('@')) return 'email';
    return 'text'; // enrollment_id or unknown
});

const inputPlaceholder = computed(() => {
    if (deliveryMethod.value === 'sms') return 'Enter your phone number (e.g. 08012345678)';
    if (form.login.includes('@')) return 'Enter your email address';
    return 'Enter email, enrollment ID, or phone number';
});
</script>

<template>

    <Head title="Forgot Password" />
    <GuestLayout>
        <Toast />

        <!-- Success message from previous attempt -->
        <Message v-if="props.status" severity="success" :closable="false" class="mb-6">
            {{ props.status }}
        </Message>

        <div class="mx-auto">
            <Card class="shadow-lg">
                <template #content>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                            <i class="pi pi-key text-3xl text-primary"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-color mb-3">Forgot Your Password?</h2>
                        <p class="text-color-secondary text-sm leading-relaxed">
                            No worries! We'll send you a 6-digit code to reset your password.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Delivery Method Selector -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full" v-if="sms_enabled">
                            <label class="relative flex items-center gap-4 rounded-lg p-2 hover:scale-105 transition-transform text-neutral-600 has-checked:border-primary has-checked:bg-primary/5 has-checked:text-neutral-900 has-checked:border has-focus:outline-2 has-focus:outline-offset-2 has-focus:outline-primary dark:has-checked:border-dark-bg-primary dark:has-checked:text-light dark:has-checked:bg-dark-bg-primary/5 dark:has-focus:outline-dark-bg-primary border outline dark:border-neutral-700">
                                <input type="radio" id="email" aria-describedby="emailDescription" class="sr-only peer"
                                    name="delivery_method" v-model="deliveryMethod" value="email" checked>
                                <div class="flex flex-col">
                                    <h3 class="font-medium" aria-hidden="true">Email</h3>
                                    <small id="emailDescription">Fast and Secured</small>
                                </div>
                            </label>

                            <label class="relative flex items-center gap-4 rounded-lg p-2 hover:scale-105 transition-transform text-neutral-600 has-checked:border-primary has-checked:bg-primary/5 has-checked:text-neutral-900 has-checked:border has-focus:outline-2 has-focus:outline-offset-2 has-focus:outline-primary dark:has-checked:border-dark-bg-primary dark:has-checked:text-light dark:has-checked:bg-dark-bg-primary/5 dark:has-focus:outline-dark-bg-primary border outline dark:border-neutral-700">
                                <input type="radio" id="sms" aria-describedby="smsDescription" class="sr-only peer"
                                    name="delivery_method" v-model="deliveryMethod" value="sms" checked>
                                <div class="flex flex-col">
                                    <h3 class="font-medium" aria-hidden="true">SMS</h3>
                                    <small id="smsDescription">No email? Use phone</small>
                                </div>
                            </label>
                        </div>


                        <!-- Unified Input Field -->
                        <div>
                            <label class="block text-sm font-medium text-color mb-2">
                                {{ deliveryMethod === 'sms' ? 'Phone Number' : 'Email or Enrollment ID' }}
                            </label>
                            <div class="relative">
                                <i class="pi absolute left-4 top-1/2 -translate-y-1/2 text-color-secondary"
                                    :class="deliveryMethod === 'sms' ? 'pi-mobile' : 'pi-user'"></i>
                                <input v-model="form.login" :type="inputType" :placeholder="inputPlaceholder" required
                                    autofocus
                                    class="w-full pl-12 pr-4 py-3 rounded-lg border focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all"
                                    :class="{ 'p-invalid': form.errors.login }" />
                            </div>
                            <Message v-if="form.errors.login" severity="error" class="mt-2">
                                {{ form.errors.login }}
                            </Message>
                        </div>

                        <!-- Submit Button -->
                        <Button type="submit" :loading="form.processing" :disabled="form.processing || !form.login"
                            severity="primary" fluid size="large" class="text-lg font-medium" icon='pi pi-send'
                            label="Send Verification Code">
                        </Button>

                        <!-- Back to Login -->
                        <div class="text-center pt-4">
                            <p class="text-sm text-color-secondary">
                                Remember your password?
                                <Link :href="route('login')" class="font-medium text-primary hover:underline">
                                Back to Login
                                </Link>
                            </p>
                        </div>
                    </form>
                </template>
            </Card>
        </div>
    </GuestLayout>
</template>

<style scoped lang="postcss">
.p-invalid {
    @apply border-red-500 focus:border-red-500 focus:ring-red-500/20;
}

input:focus {
    @apply ring-4 ring-primary/10 border-primary;
}

/* Dark mode */
.dark input {
    @apply bg-surface-800 border-surface-600 text-white;
}

.dark .bg-surface-50 {
    @apply bg-surface-800/50;
}
</style>