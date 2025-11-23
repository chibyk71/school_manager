<!-- /js/Pages/Dashboard/Index.vue -->
<script setup lang="ts">
/**
 * ----------------------------------------------------------------------
 *  Dashboard entry point
 * ----------------------------------------------------------------------
 * Props are injected by DashboardController:
 *   • component   – Vue component name (e.g. AdminDashboard)
 *   • title       – Page title for <Head> and AuthenticatedLayout
 *   • widgets     – Array of widget definitions (slug, description, …)
 *
 * The real dashboard UI lives in a component under /Dashboard/*Dashboard.vue.
 * That component receives the same `widgets` prop so it can render the
 * allowed widgets and also supply header buttons to the layout.
 */

import { computed, defineAsyncComponent, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { DashboardData, WidgetDefinition } from '@/types/dashboard';
import { ref } from 'vue';

// ------------------------------------------------------------------
// Props (typed – matches what DashboardController sends)
// ------------------------------------------------------------------
interface DashboardIndexProps {
    component: string;               // e.g. "AdminDashboard"
    title: string;                   // e.g. "Admin Dashboard"
    widgets: WidgetDefinition[];     // array of allowed widgets
    data: DashboardData;
}
const props = defineProps<DashboardIndexProps>();

// ------------------------------------------------------------------
// Dynamically import the dashboard component (lazy-loaded)
// ------------------------------------------------------------------
const DashboardComponent = computed(() => {
    // Guard against invalid component names
    const name = props.component.replace(/[^a-zA-Z0-9]/g, '');
    if (!name) return null;

    return defineAsyncComponent({
        loader: () => import(`./Dashboards/${name}.vue`),
        loadingComponent: undefined,
        errorComponent: undefined,
        delay: 0,
        timeout: 30000,
    });
});

// 🔹 Reactive array for header buttons
const layoutButtons = ref<Array<Record<string, any>>>([]);

// 🔹 Handler when child dashboard emits buttons
function handleUpdateButtons(newButtons: any[]) {
    layoutButtons.value = newButtons;
}

// ------------------------------------------------------------------
// Pass widgets + page title down to the child dashboard
// ------------------------------------------------------------------
const childProps = computed(() => ({
    widgets: props.widgets,
    pageTitle: props.title,
    data: props.data
}));
</script>

<template>
    <!-- --------------------------------------------------------------
       Page meta
       -------------------------------------------------------------- -->

    <Head :title="props.title" />

    <!-- --------------------------------------------------------------
       Layout – header/buttons come from the child dashboard component
       -------------------------------------------------------------- -->
    <AuthenticatedLayout :title="props.title" :crumb="[{ label: props.title }]" :buttons="layoutButtons">
        <!-- --------------------------------------------------------------
         Dynamically render the correct dashboard
         -------------------------------------------------------------- -->
        <component :is="DashboardComponent" v-if="DashboardComponent" v-bind="childProps" @update:buttons="handleUpdateButtons" />

        <!-- --------------------------------------------------------------
         Fallback – should never happen in production
         -------------------------------------------------------------- -->
        <div v-else class="p-6 text-center text-red-600">
            Dashboard component "{{ props.component }}" not found.
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* No extra styles needed – layout handles everything */
</style>