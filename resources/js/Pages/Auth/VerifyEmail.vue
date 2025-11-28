<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Message, Toast, InputText, ProgressSpinner } from 'primevue';
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    status?: string;
    school_id?: number;
    user?: { email: string; name: string };
}>();

const toast = useToast();
const otp = ref<string[]>(['', '', '', '', '', '']);
const isResending = ref(false);
const countdown = ref(0);
const canResend = computed(() => countdown.value === 0);

const form = useForm({
    otp: '',
});

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
const emailMasked = computed(() => {
    if (!props.user?.email) return '';
    const [local, domain] = props.user.email.split('@');
    return `${local[0]}${'*'.repeat(local.length - 2)}${local.slice(-1)}@${domain}`;
});

// Auto-focus next input
const focusNext = (index: number) => {
    const nextInput = document.querySelectorAll('.otp-input')[index + 1] as HTMLInputElement;
    nextInput?.focus();
};

// Submit OTP
const submitOtp = () => {
    const code = otp.value.join('');
    if (code.length !== 6) {
        toast.add({ severity: 'warn', summary: 'Invalid OTP', detail: 'Please enter all 6 digits', life: 4000 });
        return;
    }

    form.transform((data) => ({ ...data, otp: code })).post(route('verification.verify'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Verified!', detail: 'Your email has been verified.', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Invalid OTP', detail: 'The code you entered is incorrect or expired.', life: 5000 });
            otp.value = ['', '', '', '', '', ''];
            (document.querySelector('.otp-input') as HTMLInputElement)?.focus();
        },
    });
};

// Resend OTP
const resendOtp = async () => {
    if (!canResend.value) return;

    isResending.value = true;
    form.post(route('verification.send'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Sent!', detail: 'A new verification code has been sent.', life: 4000 });
            countdown.value = 60;
            startCountdown();
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Failed', detail: 'Could not send verification code. Try again later.', life: 5000 });
        },
        onFinish: () => {
            isResending.value = false;
        },
    });
};

// Countdown timer
const startCountdown = () => {
    const timer = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            clearInterval(timer);
        }
    }, 1000);
};

// Watch for pasted OTP
watch(otp, (newVal) => {
    const joined = newVal.join('');
    if (joined.length === 6 && /^\d+$/.test(joined)) {
        submitOtp();
    }
}, { deep: true });
</script>

<template>
    <Head title="Verify Your Email" />
    <GuestLayout>
        <Toast />

        <!-- Success message from link-based verification -->
        <Message v-if="verificationLinkSent" severity="success" :closable="false">
            A new verification link has been sent to your email address.
        </Message>

        <Card class="mx-auto">
            <template #content>
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                        <i class="pi pi-envelope text-3xl text-primary"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-color mb-2">Check Your Email</h2>
                    <p class="text-color-secondary text-sm leading-relaxed">
                        We've sent a 6-digit verification code to<br />
                        <strong class="text-primary">{{ emailMasked }}</strong>
                    </p>
                </div>

                <!-- OTP Input -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-color mb-4 text-center">Enter Verification Code</label>
                    <div class="flex justify-center gap-3">
                        <InputText
                            v-for="(digit, index) in otp"
                            :key="index"
                            v-model="otp[index]"
                            @input="focusNext(index)"
                            @paste.prevent="e => {
                                const paste = (e.clipboardData?.getData('text') || '').slice(0, 6);
                                if (/^\d+$/.test(paste)) {
                                    otp.fill('');
                                    paste.split('').forEach((char, i) => otp[i] = char);
                                }
                            }"
                            maxlength="1"
                            class="otp-input w-12 h-12 text-center text-xl font-semibold"
                            :class="{ 'p-invalid': form.errors.otp }"
                            inputmode="numeric"
                            pattern="[0-9]"
                            autocomplete="one-time-code"
                        />
                    </div>
                    <Message v-if="form.errors.otp" severity="error" class="mt-2 justify-center">
                        {{ form.errors.otp }}
                    </Message>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <Button
                        type="button"
                        label="Verify Email"
                        severity="primary"
                        fluid
                        :loading="form.processing"
                        :disabled="otp.join('').length !== 6 || form.processing"
                        @click="submitOtp"
                        icon="pi pi-check"
                    />

                    <div class="flex items-center justify-center gap-4 text-sm">
                        <span class="text-color-secondary">Didn't receive the code?</span>
                        <button
                            type="button"
                            @click="resendOtp"
                            :disabled="!canResend || isResending"
                            class="font-medium text-primary hover:underline disabled:opacity-50"
                        >
                            <span v-if="isResending">
                                <ProgressSpinner style="width: 16px; height: 16px" strokeWidth="6" class="inline-block mr-1" />
                                Sending...
                            </span>
                            <span v-else-if="countdown > 0">
                                Resend in {{ countdown }}s
                            </span>
                            <span v-else>
                                Resend Code
                            </span>
                        </button>
                    </div>

                    <div class="pt-4 border-t border-surface">
                        <Button
                            :as="Link"
                            :href="route('logout')"
                            method="post"
                            severity="secondary"
                            outlined
                            fluid
                            label="Log Out"
                            icon="pi pi-sign-out"
                        />
                    </div>
                </div>
            </template>
        </Card>
    </GuestLayout>
</template>

<style scoped lang="postcss">
.otp-input {
    @apply border rounded-lg text-center font-mono text-lg;
}
.otp-input:focus {
    @apply ring-2 ring-primary ring-offset-2 outline-none;
}
:where(.dark) .otp-input {
    @apply bg-gray-800 border-gray-700;
}
</style>
