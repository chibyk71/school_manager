<script setup lang="ts">
    import Navbar from '@/Components/menu/Navbar.vue';
import { isDarkTheme } from '@/store';
import { Head, Link } from '@inertiajs/vue3';
    import { Avatar, Button, Divider, IconField, Image, InputIcon, InputText, Menu, Menubar, OverlayBadge, Select } from 'primevue';
    import { ref } from "vue";

    defineProps<{
        canLogin?: boolean;
        canRegister?: boolean;
        laravelVersion: string;
        phpVersion: string;
    }>();
</script>

<template>

    <Head title="Welcome" />
    <Navbar />
    

</template>
