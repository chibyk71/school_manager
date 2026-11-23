<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Password, Message } from 'primevue';

defineProps<{ username?: string }>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('password.force.update'));
};
</script>

<template>
    <Head title="Change password" />
    <GuestLayout>
        <form @submit.prevent="submit">
            <Card>
                <template #content>
                    <h2 class="mb-2 text-2xl font-bold">Change your password</h2>
                    <p class="mb-4 text-sm">You must set a new password before continuing.</p>
                    <Message v-if="form.errors.password" severity="error">{{ form.errors.password }}</Message>
                    <div class="mb-3">
                        <Password v-model="form.password" placeholder="New password" :feedback="true" toggleMask fluid />
                    </div>
                    <div class="mb-4">
                        <Password v-model="form.password_confirmation" placeholder="Confirm password" :feedback="false" toggleMask fluid />
                    </div>
                    <Button type="submit" label="Update password" :loading="form.processing" fluid />
                </template>
            </Card>
        </form>
    </GuestLayout>
</template>
