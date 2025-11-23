<!-- resources/js/Layout/AuthenticatedLayout.vue -->
<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue';
import Navbar from '@/Components/menu/Navbar.vue';
import PageHeader from '@/Components/menu/PageHeader.vue';
import SidePanel from '@/Components/menu/SidePanel.vue';
import { ButtonEmits, ButtonProps, ConfirmDialog, Toast } from 'primevue';
import DynamicDialog from 'primevue/dynamicdialog';
import ResourceDialogWrapper from '@/Components/Modals/ResourceDialogWrapper.vue';

/**
 * Props for the authenticated layout.
 *
 * @prop {string} title – Page title shown in the header.
 * @prop {Array<{icon?: string; label?: string; url?: string}>} crumb – Breadcrumb items.
 * @prop {Array<ButtonProps & Partial<ButtonEmits>>} [buttons] – Optional header buttons.
 */
defineProps<{
    title: string;
    crumb: Array<{ icon?: string; label?: string; url?: string }>;
    buttons?: Array<ButtonProps & Partial<ButtonEmits> & {href?: string}>;
}>();

/**
 * ----------------------------------------------------------------------
 *  GLOBAL UI COMPONENTS
 * ----------------------------------------------------------------------
 * PrimeVue global components are mounted once per layout.  They are
 * automatically removed when the layout is destroyed.
 */
const toast = ref<InstanceType<typeof Toast> | null>(null);
const confirm = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const dynamic = ref<InstanceType<typeof DynamicDialog> | null>(null);

/* ----------------------------------------------------------------------
 *  CLEAN-UP
 * -------------------------------------------------------------------- */
onBeforeUnmount(() => {
    toast.value = null;
    confirm.value = null;
    dynamic.value = null;
});
</script>

<template>
    <!-- ------------------------------------------------------------------
       GLOBAL NAVIGATION
       ------------------------------------------------------------------ -->
    <Navbar />
    <SidePanel />

    <!-- ------------------------------------------------------------------
       PAGE CONTENT WRAPPER
       ------------------------------------------------------------------ -->
    <div class="page-wrapper">
        <div class="content blank-page !pb-12">
            <!-- Header slot – fallback to PageHeader if not overridden -->
            <slot name="header">
                <PageHeader :title="title" :crumb :buttons />
            </slot>

            <!-- Main page content -->
            <slot />
        </div>
    </div>

    <!-- ------------------------------------------------------------------
       GLOBAL PRIMEVUE DIALOGS (once per app)
       ------------------------------------------------------------------ -->
    <DynamicDialog />
    <ResourceDialogWrapper />
    <ConfirmDialog />
    <Toast />
</template>

<style scoped>
/* Tailwind already handles most spacing – keep any custom overrides here */
.page-wrapper {
    @apply flex-1 overflow-auto;
}
</style>