<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Password, useToast } from 'primevue';

const toast = useToast();

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
        onSuccess: () => {
            toast.add({severity: 'success', summary: 'Success', detail: 'Password changed successfully.', life: 3000});
        },
        onError: () => {
            toast.add({severity: 'error', summary: 'Error', detail: 'Password not changed.', life: 3000});
        }
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <form @submit.prevent="submit">
            <Card class="card">
                <template #content>
                    <div class=" mb-4">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Reset Password?</h2>
                        <p class="mb-0 text-sm/none text-color">Enter New Password & Confirm Password to get inside</p>
                    </div>
                    <TextInput label="New Password" :error="form.errors.password">
                        <template #input="{ invalid }">
                            <Password name="password" fluid toggleMask required v-model="form.password" :invalid="invalid" />
                        </template>
                    </TextInput>

                    <TextInput label="Confirm Password" :error="form.errors.password_confirmation">
                        <template #input="{ invalid }">
                            <Password v-model="form.password_confirmation" fluid toggleMask :invalid="invalid" />
                        </template>
                    </TextInput>

                    <div class="mb-3">
                        <Button type="submit" fluid :loading="form.processing">Change Password</Button>
                    </div>
                    <div class="text-center">
                        <h6 class="font-normal text-color mb-0">Return to<Link href="/login" class="hover-a "> Login</Link>
                        </h6>
                    </div>
                </template >
            </Card>
        </form>
    </GuestLayout>
</template>
