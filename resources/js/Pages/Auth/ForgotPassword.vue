<script setup lang="ts">
    import GuestLayout from '@/Layouts/GuestLayout.vue';
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import { Button, Card, IconField, InputIcon, InputText, Message, useToast } from 'primevue';

    const toast = useToast();
    const props = defineProps<{
        status?: string;
    }>();

    const form = useForm({
        email: '',
    });

    const submit = () => {
        form.post(route('password.email'),{
            onFinish: () => {
                form.reset('email');
            },
            onSuccess: () => {
                toast.add({severity: 'success', summary: 'Success', detail: 'Password reset link sent to your email.', life: 3000});
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Error', detail: 'Email not found.', life: 3000});
            }
        });
    };
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <Message severity="success" v-if="props.status">{{ props.status }}</Message>

        <form @submit.prevent="submit">
            <Card>
                <template #content>
                    <div class=" mb-4">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Forgot Password?</h2>
                        <p class="mb-0 text-sm/none">Forgot your password? No problem. Just let us know your email
            address and we will email you a password reset link that will allow
            you to choose a new one.</p>
                    </div>

                    <div class="mb-3 ">
                        <label class="form-label">Email Address</label>
                        <IconField>
                            <InputIcon class="pi pi-envelope"></InputIcon>
                            <InputText :invalid="!!form.errors.email" v-model="form.email" required autofocus type="email"  fluid class="w-full" label="Email Address" />
                        </IconField>
                        <Message severity="error" variant="simple" v-if="form.errors.email"> {{ form.errors.email }} </Message>
                    </div>
                    <div class="mb-3">
                        <Button fluid type="submit" :loading="form.processing" :disabled="form.processing" class="" severity="primary">Request Password Reset Link</Button>
                    </div>
                    <div class="text-center">
                        <h6 class="font-normal text-color mb-0">Return to
                            <Link href="/login" class="text-primary hover:text-primary-emphasis"> Login</Link>
                        </h6>
                    </div>
                </template>
            </Card>
        </form>
    </GuestLayout>
</template>
