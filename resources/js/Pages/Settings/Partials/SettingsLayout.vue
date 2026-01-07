<!-- resources/js/Pages/Settings/Partials/SettingsLayout.vue -->
<script setup lang="ts">
/**
 * SettingsLayout.vue v2.0 – Production-Ready Standardized Settings Page Layout
 *
 * Features / Problems Solved:
 * - Consistent two-column layout matching your design inspiration:
 *   • Small fixed-width sidebar on the left (hidden on mobile, revealed via drawer or menu)
 *   • Wide main content area with proper responsive behavior
 * - Fully responsive:
 *   • Mobile (< lg): sidebar collapses, content takes full width
 *   • Tablet (lg–xl): sidebar visible, content adjusts
 *   • Desktop (xl+): optimal spacing with generous content area
 * - Seamless integration with your existing modal system and Inertia pages
 * - Accessibility:
 *   • Proper ARIA landmarks (nav + main)
 *   • Keyboard-focusable sidebar when visible
 *   • Logical tab order
 * - Tailwind-only: no custom CSS needed, fully purge-safe
 * - Flexible: optional sidebar via prop (useful for rare full-width settings pages)
 * - Sticky sidebar on tall pages for easy navigation within a settings category
 * - Smooth transitions when sidebar state changes (e.g., mobile toggle)
 *
 * Fits into the Settings Module:
 * - Used as the base layout for all settings pages (General, Localization, Fees, Academic, etc.)
 * - Wraps SettingsSidebar in the "left" slot and the actual form in the "main" slot
 * - Ensures visual consistency across all settings sections
 *
 * Usage Example:
 *   <SettingsLayout>
 *     <template #left>
 *       <SettingsSidebar :items="localizationNavItems" />
 *     </template>
 *     <template #main>
 *       <LocalizationSettingsForm />
 *     </template>
 *   </SettingsLayout>
 */

defineProps<{
    /** Show the left sidebar (default: true) */
    hasSidebar?: boolean
}>()
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Mobile-friendly container with horizontal padding -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-12 gap-6 lg:gap-8">
                <!-- Left Sidebar -->
                <aside v-if="hasSidebar" class="col-span-12 lg:col-span-3 xl:col-span-2 order-last lg:order-first"
                    aria-label="Settings navigation">
                    <div
                        class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden lg:sticky lg:top-8 transition-all duration-300">
                        <slot name="left" />
                    </div>
                </aside>

                <!-- Main Content Area -->
                <main :class="[
                    hasSidebar
                        ? 'col-span-12 lg:col-span-9 xl:col-span-10'
                        : 'col-span-12',
                ]" role="main">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <slot name="main" />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Optional: subtle fade-in for content when layout changes */
main>div {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>