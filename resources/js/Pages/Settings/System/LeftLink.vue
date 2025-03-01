<script setup lang="ts">

</script>

<template>
    <div class="pt-3 d-flex flex-column list-group mb-4">
        <a :href="route('system.email')" class="d-block rounded p-2">Email Settings</a>
        <a :href="route('system.email.template')" class="d-block rounded p-2">Email Templates</a>
        <a :href="route('system.sms')" class="d-block rounded p-2">SMS Settings</a>
        <a :href="route('system.otp')" class="d-block rounded active p-2">OTP</a>
        <a :href="route('system.gdpr')" class="d-block rounded p-2">GDPR Cookies</a>
    </div>
</template>
