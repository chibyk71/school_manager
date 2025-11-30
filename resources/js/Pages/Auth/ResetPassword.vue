<script setup lang="ts">
import TextInput from '@/Components/forms/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Message, Password, Toast, ProgressSpinner } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const toast = useToast();

const props = defineProps<{
    email: string;
    token: string; // This is the OTP (6-digit string) from the backend
    school_id?: number;
}>();

// Mask email for privacy
const maskedEmail = computed(() => {
    if (!props.email) return '';
    const [name, domain] = props.email.split('@');
    return `${name[0]}${'*'.repeat(Math.max(name.length - 2, 1))}${name.slice(-1)}@${domain}`;
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

console.log(props);


const passwordStrength = ref<'weak' | 'medium' | 'strong'>('weak');
const showSuccess = ref(false);

// Watch password strength (optional enhancement)
watch(() => form.password, (val) => {
    if (!val) passwordStrength.value = 'weak';
    else if (val.length < 8) passwordStrength.value = 'weak';
    else if (/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(val)) passwordStrength.value = 'strong';
    else if (/^(?=.*[a-zA-Z])(?=.*\d)/.test(val)) passwordStrength.value = 'medium';
    else passwordStrength.value = 'weak';
});

const submit = () => {
    form.post(route('password.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess.value = true;
            toast.add({
                severity: 'success',
                summary: 'Password Reset!',
                detail: 'Your password has been changed successfully.',
                life: 5000
            });
            form.reset();
        },
        onError: (errors) => {
            const msg = errors.token
                ? 'The verification code is invalid or has expired.'
                : 'Please check your input and try again.';
            toast.add({ severity: 'error', summary: 'Failed', detail: msg, life: 6000 });
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        }
    });
};
</script>

<template>

    <Head title="Reset Password" />
    <GuestLayout>
        <Toast />

        <!-- Success State -->
        <div v-if="showSuccess" class="max-w-md mx-auto text-center py-12">
            <div
                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 mb-6">
                <i class="pi pi-check text-4xl text-green-600 dark:text-green-400"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2">Password Changed!</h2>
            <p class="text-color-secondary mb-8">You can now sign in with your new password.</p>
            <Button as="Link" :href="route('login')" label="Go to Login" severity="primary" fluid
                icon="pi pi-sign-in" />
        </div>

        <!-- Form State -->
        <div v-else class="mx-auto">
            <Card class="shadow-lg">
                <template #content>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                            <i class="pi pi-key text-3xl text-primary"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-color mb-2">Set New Password</h2>
                        <p class="text-color-secondary text-sm leading-relaxed">
                            Enter a strong password for<br>
                            <strong class="text-primary">{{ maskedEmail }}</strong>
                        </p>
                        <div class="mt-4 text-xs text-color-secondary opacity-80">
                            Verification code: <code
                                class="font-mono bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">{{ token }}</code>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Password -->
                        <TextInput label="New Password" :error="form.errors.password">
                            <template #input="{ invalid }">
                                <Password v-model="form.password" :invalid="invalid" fluid toggle-mask :feedback="true"
                                    prompt-label="Choose a password" weak-label="Too weak" medium-label="Medium"
                                    strong-label="Strong" :class="{
                                        'p-password-weak': passwordStrength === 'weak',
                                        'p-password-medium': passwordStrength === 'medium',
                                        'p-password-strong': passwordStrength === 'strong'
                                    }" input-class="w-full" required />
                            </template>
                        </TextInput>

                        <!-- Confirm Password -->
                        <TextInput label="Confirm Password" :error="form.errors.password_confirmation">
                            <template #input="{ invalid }">
                                <Password v-model="form.password_confirmation" :invalid="invalid" fluid toggle-mask
                                    :feedback="false" input-class="w-full" required />
                            </template>
                        </TextInput>

                        <!-- Show token error clearly -->
                        <Message v-if="form.errors.token" severity="error" class="justify-center">
                            {{ form.errors.token }}
                        </Message>

                        <Button type="submit" label="Change Password" severity="primary" fluid
                            :loading="form.processing"
                            :disabled="form.processing || !form.password || form.password !== form.password_confirmation"
                            icon="pi pi-lock-open" class="mt-4" />

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

<style scoped>
:deep(.p-password-weak .p-password-meter) {
    background: linear-gradient(to right, #ef4444 0%, #ef4444 33%, transparent 33%);
}

:deep(.p-password-medium .p-password-meter) {
    background: linear-gradient(to right, #f59e0b 0%, #f59e0b 66%, transparent 66%);
}

:deep(.p-password-strong .p-password-meter) {
    background: linear-gradient(to right, #22c55e 0%, #22c55e 100%);
}
</style>
