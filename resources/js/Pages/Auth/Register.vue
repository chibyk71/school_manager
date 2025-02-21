<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, Image, Password } from 'primevue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <Head title="Register" />
    <GuestLayout>
        <form @submit.prevent="submit">
            <Card class="card">
                <template #content>

                    <div class=" mb-4">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Register</h2>
                        <p class="mb-0 text-sm/none text-color">Please enter your details to sign in</p>
                    </div>
                    <div class="mt-4">
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
                        <div class="mb-3 ">
                            <TextInput label="Name" v-model="form.name" icon="pi pi-user" :error="form.errors.name" />

                            <TextInput label="Email" v-model="form.email" icon="pi pi-envelope" :error="form.errors.email" />

                            <TextInput label="Password" icon="pi pi-lock" :error="form.errors.password">
                                <template #input="{ invalid }">
                                    <Password v-model="form.password" fluid toggleMask :invalid="invalid" />
                                </template>
                            </TextInput>

                            <TextInput label="Confirm Password" icon="pi pi-lock" :error="form.errors.password_confirmation">
                                <template #input="{ invalid }">
                                    <Password v-model="form.password_confirmation" fluid toggleMask :invalid="invalid" />
                                </template>
                            </TextInput>
                        </div>
                        <div class="flex items-center mb-3">
                            <div class="flex items-center">
                                <Checkbox required v-model="form.terms" />
                                <!-- TODO: replace link with actual link for terms page -->
                                <h6 class="font-normal text-color ml-2 mb-0">I Agree to<Link href="#"
                                        class="text-primary hover:text-primary-emphasis"> Terms & Privacy</Link>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <Button type="submit" fluid :loading="form.processing" class="">Sign Up</Button>
                    </div>
                    <div class="text-center">
                        <h6 class="font-normal text-color mb-0">Already have an account?<Link
                                href="/login" class="hover-a"> Sign In</Link>
                        </h6>
                    </div>
                </template>
            </Card>
        </form>
    </GuestLayout>
</template>
