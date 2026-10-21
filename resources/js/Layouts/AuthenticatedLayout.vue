<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Navbar from '@/Components/menu/Navbar.vue';
import PageHeader from '@/Components/menu/PageHeader.vue';
import SidePanel from '@/Components/menu/SidePanel.vue';
import { ButtonEmits, ButtonProps, ConfirmDialog, Toast } from 'primevue';
import DynamicDialog from 'primevue/dynamicdialog';
import ResourceDialogWrapper from '@/Components/Modals/ResourceDialog.vue';
import {
    isLayoutMobileViewport,
    mobileSidebarOpen,
    SIDEBAR_WIDTH_COLLAPSED,
    SIDEBAR_WIDTH_EXPANDED,
    sidebarCollapsed,
} from '@/store/layout';
import { usePage } from '@inertiajs/vue3';

defineProps<{
    title: string;
    crumb: Array<{ icon?: string; label?: string; url?: string }>;
    buttons?: Array<ButtonProps & Partial<ButtonEmits> & { href?: string }>;
    canSeeTrashed?: boolean;
}>();

const page = usePage();
const isMobile = ref(false);

const contentOffset = computed(() => {
    if (isMobile.value) return 0;
    return sidebarCollapsed.value ? SIDEBAR_WIDTH_COLLAPSED : SIDEBAR_WIDTH_EXPANDED;
});

const closeMobileSidebar = () => {
    mobileSidebarOpen.value = false;
};

const syncViewport = () => {
    const mobile = isLayoutMobileViewport();
    isMobile.value = mobile;
    // Crossing into desktop: force-close drawer/backdrop so state cannot stick
    if (!mobile) {
        mobileSidebarOpen.value = false;
    }
};

watch(
    () => page.url,
    () => {
        mobileSidebarOpen.value = false;
    },
);

onMounted(() => {
    syncViewport();
    window.addEventListener('resize', syncViewport);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncViewport);
});
</script>

<template>
    <div class="main-wrapper relative min-h-screen bg-white dark:bg-dark-bg-primary">
        <Transition name="fade">
            <div
                v-if="mobileSidebarOpen"
                class="fixed inset-0 z-40 bg-black/40 min-[992px]:hidden"
                aria-hidden="true"
                @click="closeMobileSidebar"
            />
        </Transition>

        <SidePanel />

        <div
            class="page-wrapper relative z-10 flex min-h-screen flex-col transition-[margin] duration-300 ease-in-out"
            :style="{ marginLeft: `${contentOffset}px` }"
        >
            <Navbar />

            <div class="content min-h-0 flex-1 overflow-auto p-4 pb-12 lg:p-6 dark:bg-dark-bg-primary">
                <slot name="header">
                    <PageHeader :title="title" :crumb :buttons :can-see-trashed />
                </slot>

                <slot />
            </div>
        </div>
    </div>

    <DynamicDialog />
    <ResourceDialogWrapper />
    <ConfirmDialog />
    <Toast />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Mobile: content flush to edge (drawer overlays). Keep in sync with LAYOUT_DESKTOP_MIN. */
@media (max-width: 991px) {
    .page-wrapper {
        margin-left: 0 !important;
    }
}
</style>
