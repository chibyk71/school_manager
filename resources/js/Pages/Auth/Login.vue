<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, IconField, Image, InputIcon, InputText, Message, Password } from 'primevue';

defineProps<{
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;
}>();

const form = useForm({
    login: 'admin@demo.academy',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Log in" />
    <GuestLayout>
        <Message severity="success" v-if="status">{{ status }}</Message>

        <form @submit.prevent="submit">
            <Card>
              <template #content>
                    <div class=" mb-4">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Welcome</h2>
                        <p class="mb-0 text-sm/none text-color">Please enter your details to sign in</p>
                    </div>
                    <div class="mt-4">
                        <div
                            class="flex items-center justify-center flex-wrap gap-x-2">
                            <div class="text-center flex-1">
                                <Button fluid severity="primary" class="">
                                    <Image class="img-fluid m-1" src="assets/img/icons/facebook-logo.svg" alt="Facebook" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid class="" severity="" variant="outlined" plain >
                                    <Image class="img-fluid m-1" src="assets/img/icons/google-logo.svg" alt="Facebook" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid class="" severity="contrast">
                                    <img class="img-fluid m-1" src="assets/img/icons/apple-logo.svg" alt="Apple">
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div class="login-or">
                        <span class="span-or">Or</span>
                    </div>
                    <div class="mb-2">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <IconField>
                                <InputIcon class="pi pi-envelope"></InputIcon>
                                <InputText :invalid="!!form.errors.login" v-model="form.login" required autofocus type="email"  fluid label="Email Address" />
                            </IconField>
                            <Message severity="error" variant="simple" v-if="form.errors.login"> {{ form.errors.login }} </Message>
                        </div>
                        <TextInput label="Password" icon="pi pi-lock" :error="form.errors.password">
                            <template #input="{ invalid }">
                                <Password :invalid="invalid" v-model="form.password" fluid toggleMask></Password>
                            </template>
                        </TextInput>
                    </div>

                    <div class="flex items-center mb-3">
                        <div class="flex items-center">
                            <Checkbox binary v-model="form.remember" />
                            <p class="ml-2 mb-0 ">Remember Me</p>
                        </div>
                        <div class="text-end ml-auto" v-if="canResetPassword">
                            <Link :href="route('password.request')" class="text-red-500">Forgot
                                Password?</Link>
                        </div>
                    </div>
                    <div class="mb-3">
                        <Button type="submit" loading-icon="pi pi-spinner" fluid :loading="form.processing" label="Sign In" class="" severity="primary"></Button>
                    </div>
                    <div class="text-center">
                        <h6 v-if="canRegister" class="fw-normal text-dark mb-0">Don't have an account? <Link :href="route('register')" class="hover-a "> Create Account</Link>
                        </h6>
                    </div>
              </template>
            </Card>
        </form>
    </GuestLayout>
</template>
