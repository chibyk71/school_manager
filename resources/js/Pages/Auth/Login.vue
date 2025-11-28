<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, IconField, Image, InputIcon, InputText, Message, Password, Toast } from 'primevue';
import { ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';

defineProps<{
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;
    school_id?: number | null;
}>();

const toast = useToast();
const loginType = ref<'email' | 'enrollment_id'>('email'); // Auto-detect login type
const form = useForm({
    login: 'admin@demo.academy',
    password: '',
    remember: false,
    school_id: null, // Include if needed for multi-tenant
});

const submit = () => {
    form.post(route('login'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Logged in successfully', life: 3000 });
        },
        onError: (errors) => {
            if (errors.login && errors.login.includes('rate limit')) {
                toast.add({ severity: 'warn', summary: 'Rate Limited', detail: 'Too many attempts. Try again later.', life: 5000 });
            } else {
                toast.add({ severity: 'error', summary: 'Login Failed', detail: 'Please check your credentials.', life: 3000 });
            }
        },
        onFinish: () => {
            form.reset('password');
        },
    });
};

// Auto-detect login type (email vs enrollment_id)
watch(() => form.login, (value) => {
    loginType.value = value.includes('@') ? 'email' : 'enrollment_id';
});
</script>

<template>
    <Head title="Log in" />
    <GuestLayout>
        <Toast />
        <Message severity="success" v-if="status">{{ status }}</Message>

        <form @submit.prevent="submit">
            <Card>
                <template #content>
                    <div class="mb-6">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Welcome</h2>
                        <p class="mb-0 text-base/none text-color">Please enter your details to sign in</p>
                    </div>
                    <div class="mt-6">
                        <div class="flex items-center justify-center flex-wrap gap-x-2">
                            <div class="text-center flex-1">
                                <Button class="bg-primary" fluid>
                                    <Image class="img-fluid m-1" src="assets/img/icons/facebook-logo.svg" alt="Facebook" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid severity="secondary" outlined>
                                    <Image class="img-fluid m-1" src="assets/img/icons/google-logo.svg" alt="Google" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid severity="contrast">
                                    <Image class="img-fluid m-1" src="assets/img/icons/apple-logo.svg" alt="Apple" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div class="login-or">
                        <span class="span-or">Or continue with</span>
                    </div>
                    <div class="mb-2">
                        <TextInput :label="loginType === 'email' ? 'Email Address' : 'Enrollment ID'" icon="pi pi-envelope" :error="form.errors.login">
                            <template #input="{ invalid }">
                                <InputText :id="`login-input`" :invalid="invalid" v-model="form.login" required autofocus :type="loginType === 'email' ? 'email' : 'text'" fluid />
                            </template>
                        </TextInput>
                        <TextInput label="Password" icon="pi pi-lock" :error="form.errors.password">
                            <template #input="{ invalid }">
                                <Password :invalid="invalid" v-model="form.password" fluid toggleMask :feedback="true" />
                            </template>
                        </TextInput>
                    </div>
                    <div class="flex items-center mb-4 justify-between">
                        <div class="flex items-center">
                            <Checkbox binary v-model="form.remember" />
                            <label class="ml-2 mb-0 text-sm text-gray-600 dark:text-gray-400">Remember Me</label>
                        </div>
                        <div v-if="canResetPassword">
                            <Link :href="route('password.request')" class="text-primary hover:underline text-sm">Forgot Password?</Link>
                        </div>
                    </div>
                    <div class="mb-4">
                        <Button type="submit" :loading="form.processing" label="Sign In" severity="primary" fluid icon="pi pi-sign-in" />
                    </div>
                    <div class="text-center text-sm" v-if="canRegister">
                        <p class="font-normal text-gray-600 dark:text-gray-400 mb-0">Don't have an account? <Link :href="route('register')" class="hover-a text-primary">Create Account</Link></p>
                    </div>
                </template>
            </Card>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Integrate auth.postcss styles */
.login-or {
    font-size: 15px;
    color: #515b73;
    font-weight: 500;
    margin: 15px 0;
    position: relative;
}
.login-or::after,
.login-or::before {
    content: "";
    border-top: 1px solid #cdd0d7;
    position: absolute;
    top: 50%;
    width: calc(50% - 20px); /* Adjust for 'Or' width */
}
.login-or::before {
    left: 0;
}
.login-or::after {
    right: 0;
}
.span-or {
    background: var(--p-content-background);
    padding: 0 10px;
    z-index: 1;
    position: relative;
}

/* Dark mode overrides from app.css */
:where(.dark, .dark *) .login-or {
    color: var(--color-gray-300);
}
:where(.dark, .dark *) .login-or::after,
:where(.dark, .dark *) .login-or::before {
    border-color: var(--color-gray-700);
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .flex-wrap gap-x-2 {
        flex-direction: column;
        row-gap: 2;
    }
}
</style>
