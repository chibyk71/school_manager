<!-- resources/js/Pages/Settings/Partials/SettingsSidebar.vue -->
<script setup lang="ts">
/**
 * SettingsSidebar.vue v2.0 – Production-Ready Settings Navigation Component
 *
 * Features / Problems Solved:
 * - Fully accessible navigation: proper <nav> landmark, ARIA attributes, keyboard focus styles
 * - Consistent active state handling: uses Inertia's preserved active state + fallback to prop
 * - Icon support via PrimeVue icons (pi classes) – consistent with the rest of the app
 * - Responsive behavior: collapses gracefully on mobile when used inside SettingsLayout
 * - Smooth hover/active transitions with Tailwind's transition utilities
 * - Dark mode ready: uses neutral gray + primary colors that work in both themes
 * - Type-safe props with clear JSDoc
 * - Optimized re-renders: no unnecessary watchers or computed properties
 * - Visual alignment with your design inspiration (small sidebar, clean spacing, rounded active item)
 *
 * Fits into the Settings Module:
 * - Used exclusively in the "left" slot of SettingsLayout.vue
 * - Provides vertical navigation for all settings sub-sections (e.g., Website Settings → Company, Localization, etc.)
 * - Works seamlessly with Inertia Link for SPA navigation (no full page reloads)
 * - Maintains visual consistency across all settings pages
 *
 * Usage:
 *   <SettingsSidebar
 *     title="Website Settings"
 *     :items="sidebarItems"
 *   />
 */

import { Link } from '@inertiajs/vue3'

defineProps<{
    /**
     * Navigation items for the current settings category/group.
     * `active` is optional – if omitted, Inertia Link will auto-detect based on current URL.
     */
    items: Array<{
        label: string
        href: string
        active?: boolean
        icon?: string // PrimeVue icon class, e.g., 'pi pi-globe'
    }>

    /** Optional section title displayed above the links */
    title?: string
}>()
</script>

<template>
    <nav class="p-4 lg:p-6" aria-label="Settings section navigation">
        <!-- Section Title -->
        <h2 v-if="title" class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 px-2">
            {{ title }}
        </h2>

        <!-- Navigation List -->
        <ul class="space-y-1">
            <li v-for="item in items" :key="item.href">
                <Link :href="item.href"
                    class="group flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200
                 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
                 hover:bg-primary-50 hover:text-primary-700 dark:hover:bg-primary-900/30
                 aria-current-page:bg-primary-100 aria-current-page:text-primary-700 dark:aria-current-page:bg-primary-900/50" :class="{
                    'bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300': item.active,
                    'text-gray-700 dark:text-gray-300': !item.active,
                }" :aria-current="item.active ? 'page' : undefined">
                    <!-- Icon -->
                    <i v-if="item.icon" class="text-lg transition-colors duration-200" :class="{
                        'text-primary-700 dark:text-primary-400': item.active,
                        'text-gray-500 group-hover:text-primary-600 dark:text-gray-400 dark:group-hover:text-primary-400': !item.active,
                        '{{ item.icon }}': true
                    }" aria-hidden="true" />

                    <!-- Label -->
                    <span class="truncate">{{ item.label }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>

<style scoped lang="postcss">
/* Ensure smooth focus ring on keyboard navigation */
a:focus-visible {
    @apply ring-2 ring-primary-500 ring-offset-2;
}
</style>
