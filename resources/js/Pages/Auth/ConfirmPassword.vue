<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Password } from 'primevue';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Confirm Password" />
    <GuestLayout>

        <Card>
            <template #content>
                <div class=" mb-4">
                    <h2 class="mb-2 text-2xl/none font-bold text-color">Confirm Password</h2>
                    <p class="mb-0 text-sm/none text-color">This is a secure area of the application. Please confirm your
                        password before continuing.</p>
                </div>


            <form @submit.prevent="submit">
                <TextInput label="Password" icon="pi pi-lock" :error="form.errors.password">
                    <template #input="{invalid}">
                        <Password required autofocus :invalid="invalid" v-model="form.password" toggleMask></Password>
                    </template>
                </TextInput>

                <div class="mt-4">
                    <Button type="submit" :loading="form.processing" fluid>
                        Confirm
                    </Button>
                </div>
            </form>
            </template>
        </Card>
    </GuestLayout>
</template>
