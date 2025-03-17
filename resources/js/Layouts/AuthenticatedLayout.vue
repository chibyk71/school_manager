<script setup lang="ts">
    import { ref } from 'vue';
    import Navbar from '@/Components/menu/Navbar.vue';
    import PageHeader from '@/Components/menu/PageHeader.vue';
    import SidePanel from '@/Components/menu/SidePanel.vue';
    import { ButtonEmits, ConfirmDialog, Toast, type ButtonProps } from 'primevue';
    import DynamicDialog from 'primevue/dynamicdialog';


    defineProps<{
        title: string,
        crumb: {
            icon?: string,
            label?: string,
            url?: string
        }[],
        buttons?: Array<(ButtonProps & Partial<ButtonEmits>)>
    }>()
</script>

<template>
    <Navbar />
    <SidePanel />
    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content blank-page !pb-12">
            <slot  name="header"><PageHeader :title="title" :crumb :buttons /> </slot>
            <slot></slot>
        </div>
    </div>
    <DynamicDialog />
    <ConfirmDialog></ConfirmDialog>
    <Toast></Toast>
</template>
