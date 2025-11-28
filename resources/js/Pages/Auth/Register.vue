<script setup lang="ts">
import TextInput from '@/Components/inputs/textInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, Image, InputIcon, InputText, Message, Password, Select, Step, StepList, Stepper, Steps, Toast } from 'primevue';
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    show_terms?: boolean;
    school_id?: string | null;
    schools?: Array<{ id: number; name: string }>; // If multi-school selection
    status?: string;
}>();

const toast = useToast();
const activeStep = ref(1);
const steps = ref([
    { label: 'Account Details' },
    { label: 'Personal Info' },
]);

const form = useForm({
    name: '',
    email: '',
    enrollment_id: '', // Optional based on settings
    password: '',
    password_confirmation: '',
    terms: false,
    school_id: props.school_id ?? null, // For multi-tenant
});

const nextStep = () => {
    // Client-side validation per step
    if (activeStep.value === 1) {
        form.clearErrors();
        if (!form.email || !form.password || !form.password_confirmation) {
            toast.add({ severity: 'warn', summary: 'Incomplete', detail: 'Please fill all required fields.', life: 3000 });
            return;
        }
        if (form.password !== form.password_confirmation) {
            form.errors.password_confirmation = 'Passwords do not match.';
            return;
        }
        activeStep.value++;
    } else {
        submit();
    }
};

const submit = () => {
    form.post(route('register'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Account created successfully.', life: 3000 });
            form.reset();
            activeStep.value = 0;
        },
        onError: (errors) => {
            toast.add({ severity: 'error', summary: 'Registration Failed', detail: 'Please check the form for errors.', life: 3000 });
            if (errors.password) activeStep.value = 0; // Jump back if password issue
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};

</script>

<template>

    <Head title="Register" />
    <GuestLayout>
        <Toast />
        <Message severity="success" v-if="status">{{ status }}</Message>

        <form @submit.prevent="nextStep">
            <Card>
                <template #content>
                    <div class="mb-6">
                        <h2 class="mb-2 text-2xl/none font-bold text-color">Create Account</h2>
                        <p class="mb-0 text-base/none text-color">Join us and get started today</p>
                    </div>
                    <div class="mt-6">
                        <div class="flex items-center justify-center flex-wrap gap-x-2">
                            <div class="text-center flex-1">
                                <Button class="bg-primary" fluid>
                                    <Image class="img-fluid m-1" src="assets/img/icons/facebook-logo.svg"
                                        alt="Facebook" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid severity="secondary" outlined>
                                    <Image class="img-fluid m-1" src="assets/img/icons/google-logo.svg"
                                        alt="Google" />
                                </Button>
                            </div>
                            <div class="text-center flex-1">
                                <Button fluid severity="contrast">
                                    <Image class="img-fluid m-1" src="assets/img/icons/apple-logo.svg"
                                        alt="Apple" />
                                </Button>
                            </div>
                        </div>
                        <div class="login-or mb-4">
                            <span class="span-or">Or sign up with email</span>
                        </div>
                        <Stepper :value="activeStep" class="basis-[50rem]" linear>
                            <StepList>
                                <Step v-for="(step, index) in steps" :key="index" :value="index + 1">{{ step.label }}</Step>
                            </StepList>
                        </Stepper>
                        <div v-if="activeStep === 1">
                            <TextInput label="Email Address" icon="pi pi-envelope" :error="form.errors.email">
                                <template #input="{ invalid }">
                                    <InputText v-model="form.email" :invalid="invalid" required autofocus type="email"
                                        fluid />
                                </template>
                            </TextInput>
                            <TextInput label="Password" icon="pi pi-lock" :error="form.errors.password">
                                <template #input="{ invalid }">
                                    <Password v-model="form.password" :invalid="invalid" fluid toggleMask
                                        :feedback="true" />
                                </template>
                            </TextInput>
                            <TextInput label="Confirm Password" icon="pi pi-lock"
                                :error="form.errors.password_confirmation">
                                <template #input="{ invalid }">
                                    <Password v-model="form.password_confirmation" :invalid="invalid" fluid toggleMask
                                        :feedback="false" />
                                </template>
                            </TextInput>
                            <Button label="Next" severity="primary" fluid @click="nextStep" class="mt-4" />
                        </div>
                        <div v-if="activeStep === 2">
                            <TextInput label="Full Name" icon="pi pi-user" :error="form.errors.name">
                                <template #input="{ invalid }">
                                    <InputText v-model="form.name" :invalid="invalid" required fluid />
                                </template>
                            </TextInput>
                            <TextInput v-if="schools && schools.length > 0" label="Select School" icon="pi pi-building"
                                :error="form.errors.school_id">
                                <template #input="{ invalid }">
                                    <Select v-model="form.school_id" :options="schools" optionLabel="name"
                                        optionValue="id" :invalid="invalid" fluid placeholder="Choose a school" />
                                </template>
                            </TextInput>
                            <div v-if="show_terms" class="flex items-center mb-4">
                                <Checkbox v-model="form.terms" binary required />
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">I agree to the
                                    <Link href="route('terms.show')" class="text-primary hover:underline">Terms &
                                    Privacy</Link>
                                </label>
                                <Message severity="error" v-if="form.errors.terms">{{ form.errors.terms }}</Message>
                            </div>
                            <Button type="submit" :loading="form.processing" label="Sign Up" severity="primary" fluid
                                icon="pi pi-user-plus" class="mt-4" />
                        </div>
                    </div>
                    <div class="text-center mt-4 text-sm">
                        <p class="font-normal text-gray-600 dark:text-gray-400 mb-0">Already have an account?
                            <Link :href="route('login')" class="hover-a text-primary">Sign In</Link>
                        </p>
                    </div>
                </template>
            </Card>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Reuse from auth.postcss */
.login-or {
    text-align: center;
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
    width: calc(50% - 20px);
}

.login-or::before {
    left: 0;
}

.login-or::after {
    right: 0;
}

.span-or {
    background-color: var(--p-content-background);
    padding: 0 10px;
    z-index: 1;
    position: relative;
}

/* Dark mode */
:where(.dark, .dark *) .login-or {
    color: var(--color-gray-300);
}

:where(.dark, .dark *) .login-or::after,
:where(.dark, .dark *) .login-or::before {
    border-color: var(--color-gray-700);
}
</style>
