<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Message, Password, Toast } from 'primevue';
import { useToast } from 'primevue/usetoast';
import { nextTick, ref } from 'vue';

const toast = useToast();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Confirmed',
                detail: 'Password confirmed. You may continue.',
                life: 3000
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Incorrect Password',
                detail: 'The password you entered is incorrect.',
                life: 5000
            });
            form.reset();
            // Re-focus password field
            nextTick(() => {
                const el = document.querySelector('input[type="password"]') as HTMLInputElement;
                el?.focus();
            });
        },
        onFinish: () => {
            form.reset();
        }
    });
};
</script>

<template>

    <Head title="Confirm Password" />
    <GuestLayout>
        <Toast />

        <div class="mx-auto">
            <Card class="shadow-xl">
                <template #content>
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <div
                            class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-orange-100 dark:bg-orange-900/30 mb-5">
                            <i class="pi pi-shield text-4xl text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-color mb-3">Confirm Your Password</h2>
                        <p class="text-color-secondary text-sm leading-relaxed px-4">
                            This is a secure area of the application.<br>
                            Please confirm your password to continue.
                        </p>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-color mb-3">
                                Enter your password
                            </label>
                            <Password v-model="form.password" :invalid="!!form.errors.password" fluid toggle-mask
                                autofocus required placeholder="Enter your current password" :feedback="false"
                                input-class="w-full" class="w-full" />
                            <Message v-if="form.errors.password" severity="error" class="mt-2">
                                {{ form.errors.password }}
                            </Message>
                        </div>

                        <!-- Submit Button -->
                        <Button type="submit" label="Confirm Password" severity="primary" fluid size="large"
                            :loading="form.processing" :disabled="form.processing || !form.password"
                            icon="pi pi-check-circle" class="font-medium text-lg py-3" />

                        <!-- Helpful Links -->
                        <div class="text-center space-y-2 pt-4 border-t border-surface">
                            <p class="text-sm text-color-secondary">
                                Forgot your password?
                                <Link :href="route('password.request')"
                                    class="font-medium text-primary hover:underline">
                                Reset it here
                                </Link>
                            </p>
                            <p class="text-xs text-color-secondary opacity-75">
                                Or
                                <Link :href="route('logout')" method="post" as="button"
                                    class="text-primary hover:underline">
                                log out
                                </Link>
                                and sign in again
                            </p>
                        </div>
                    </form>
                </template>
            </Card>

            <!-- Footer Note -->
            <p class="text-center text-xs text-color-secondary mt-8 opacity-70">
                This extra step helps keep your account secure
            </p>
        </div>
    </GuestLayout>
</template>

<style scoped lang="postcss">
/* Enhance PrimeVue Password field focus */
:deep(.p-password input:focus) {
    @apply ring-4 ring-primary/10 border-primary shadow-lg;
}

/* Dark mode adjustments */
.dark :deep(.p-password input) {
    @apply bg-surface-800 border-surface-600;
}

.dark .border-surface {
    @apply border-gray-700;
}
</style>