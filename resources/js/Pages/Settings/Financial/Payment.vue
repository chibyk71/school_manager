<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import EmailCard from '../System/EmailCard.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm<{ [x: string]: boolean }>({
    paypal: true,
    stripe: false,
    braintree: false,
    authorize_net: false,
    razorpay: true,
    transferwise: false,
    revolut: false,
    payoneer: false,
    two_checkout: true,
    paytm: false,
    skrill: true,
    midtrans: false,
    pytorch: true,
    wire_transfer: false,
    cash_on_delivery: false,
});

const submit = () => {
    form.post(route('settings.payment-gateways'), {
        preserveScroll: true,
        onSuccess: () => {
            //
        },

    });
}
</script>

<template>
    <AuthenticatedLayout title="Payment Settings" :crumb="[{ label: 'Settings' }, { label: 'Finance' }, { label: 'Payment' }]">
        <SettingsLayout>
            <template #main>
                <div class="pl-3">
                    <form :action="route('settings.payment-gateways')" method="post" @submit.prevent="submit">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Payment Gateways</h5>
                                <p>Payments Settings Configuration</p>
                            </div>
                            <div class="mb-3">
                                <a href="email.html" class="btn btn-outline-light bg-white btn-icon me-2"><i
                                        class="ti ti-mail-share"></i></a>
                                <button class="btn btn-light me-2" type="button">Cancel</button>
                                <button class="btn btn-primary" type="submit">Save</button>
                            </div>
                        </div>
                        <div class="grid xxl:grid-cols-3 xl:grid-cols-2 gap-4">
                            <EmailCard title="PayPal" image="assets/img/payment-gateway/payment-gateway-01.svg"
                                content="PayPal is the faster, safer way to send and receive money or make an online payment."
                                status />
                            <EmailCard title="Stripe" image="assets/img/payment-gateway/payment-gateway-02.svg"
                                content="APIs to accept credit cards, manage subscriptions, send money." />

                            <EmailCard title="Braintree" image="assets/img/payment-gateway/payment-gateway-03.svg"
                                content="Braintree offers more fraud protection and security features." />
                            <EmailCard title="Authorize.Net" image="assets/img/payment-gateway/payment-gateway-04.svg"
                                content="Works stably and reliably and features are valuable and necessary for any software." />

                            <EmailCard title="Razorpay" image="assets/img/payment-gateway/payment-gateway-05.svg"
                                content="Razorpay is a comprehensive payment gateway and financial solutions provider in India."
                                status />
                            <EmailCard title="TransferWise" image="assets/img/payment-gateway/payment-gateway-06.svg"
                                content="Allows send international money transfers and payments quickly with low fees." />

                            <EmailCard title="Revolut" image="assets/img/payment-gateway/payment-gateway-07.svg"
                                content="Replaces your physical cards and cash with an easier, safer, more private and secure." />

                            <EmailCard title="Payoneer" image="assets/img/payment-gateway/payment-gateway-08.svg"
                                content="Fast, Low-Cost Solution for your International Business." />

                            <EmailCard title="2Checkout" image="assets/img/payment-gateway/payment-gateway-09.svg"
                                content="Provide payment solution to individuals to make payments using credit card."
                                status />

                            <EmailCard title="Paytm" image="assets/img/payment-gateway/payment-gateway-10.svg"
                                content="Paytm stands for Pay through mobile and it is India's largest mobile payments." />

                            <EmailCard title="Skrill" image="assets/img/payment-gateway/payment-gateway-11.svg"
                                content="Online payment platform that enables to send and receive money via emails."
                                status />

                            <EmailCard title="Midtrans" image="assets/img/payment-gateway/payment-gateway-12.svg"
                                content="Midtrans provides the maximum number of payment methods across payment gateways." />

                            <EmailCard title="PyTorch" image="assets/img/payment-gateway/payment-gateway-13.svg"
                                content="PyTorch is a network through which your customers transfer funds to you."
                                status />

                            <EmailCard title="Wire Transfer" image="assets/img/payment-gateway/payment-gateway-14.svg"
                                content="Direct transfer of funds from one bank account into another." />

                            <EmailCard title="Cash on Delivery"
                                image="assets/img/payment-gateway/payment-gateway-15.svg"
                                content="Indicating that goods must be paid for at the time of delivery." />
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
