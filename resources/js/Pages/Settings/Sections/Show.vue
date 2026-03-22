<script setup lang="ts">
/**
 * Pages/Sections/Show.vue
 *
 * Section detail page — the primary management surface for a school section.
 *
 * Purpose:
 * ─────────────────────────────────────────────────────────────────────────────
 * This page serves as the container for everything that belongs to a school
 * section. It uses a tab layout so different aspects of the section (class
 * levels, staff, settings, etc.) can be managed without leaving the page.
 *
 * Current tabs:
 *   - Class Levels  → ClassLevelsTab.vue (fully implemented)
 *   - Staff         → stub (future module)
 *   - Settings      → stub (future module)
 *
 * Features implemented:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Inertia page receiving section data as props
 * - Breadcrumb navigation back to sections list
 * - Section header with name, description, and status badge
 * - Tab navigation that preserves the active tab in the URL query string
 *   (?tab=class-levels) so page refreshes and shared links land on the
 *   correct tab
 * - Responsive layout — stacked on mobile, side-by-side header + tabs on desktop
 * - Active tab is driven by the URL query param, defaulting to 'class-levels'
 * - Each tab content is lazy — only the active tab renders its component,
 *   avoiding unnecessary API calls for inactive tabs
 *
 * Props (from Inertia / SectionController::show):
 * ─────────────────────────────────────────────────────────────────────────────
 * - section: the full SchoolSection model data including school relationship
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Route: GET /sections/{section}  → SectionController::show
 * - ClassLevelsTab.vue is rendered inside the "Class Levels" tab panel
 * - Future tabs (Staff, Subjects, Settings) drop in here without modifying
 *   this component — just add a tab definition and a matching panel
 */

import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ClassLevelsTab from './Partials/ClassLevelsTab.vue'
import { Badge } from 'primevue'

// ── Types ─────────────────────────────────────────────────────────────────────

interface SchoolSection {
    id: string
    name: string
    description: string | null
    is_active: boolean
    school: {
        id: string
        name: string
    }
    created_at: string
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    section: SchoolSection
}>()

// ── Tab management ────────────────────────────────────────────────────────────

/**
 * Tab definitions.
 * To add a new tab, append an entry here and add a matching <template> below.
 * key must be URL-safe (used as the ?tab= query param value).
 */
const tabs = [
    { key: 'class-levels', label: 'Class Levels',  icon: 'pi pi-list' },
    { key: 'staff',        label: 'Staff',          icon: 'pi pi-users' },
    { key: 'settings',     label: 'Settings',       icon: 'pi pi-cog' },
] as const

type TabKey = typeof tabs[number]['key']

const page = usePage()

/**
 * Active tab is driven by the ?tab= query param.
 * Defaults to 'class-levels' if not present or invalid.
 */
const activeTab = computed<TabKey>(() => {
    const param = page.url?.split('?')[1]?.split('&').find((p: string) => p.startsWith('tab='))?.split('=')[1] as string | undefined
    const valid  = tabs.map(t => t.key) as string[]
    return (valid.includes(param ?? '') ? param : 'class-levels') as TabKey
})

/**
 * Navigate to a tab by updating the URL query param.
 * Uses Inertia's router.get with preserveState so the section prop is not
 * re-fetched — only the URL changes, which triggers activeTab to recompute.
 */
const switchTab = (key: TabKey) => {
    if (key === activeTab.value) return

    router.get(
        route('sections.show', props.section.id),
        { tab: key },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}
</script>

<template>
    <AuthenticatedLayout
        :title="section.name"
        :crumb="[
            { label: 'Sections', url: route('sections.index') },
            { label: section.name },
        ]"
    >
        <!-- ── Section Header ─────────────────────────────────────────────── -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">

                <!-- Left: name + meta -->
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ section.name }}
                        </h1>
                        <Badge
                            :value="section.is_active ? 'Active' : 'Inactive'"
                            :severity="section.is_active ? 'success' : 'secondary'"
                        />
                    </div>

                    <p
                        v-if="section.description"
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-xl"
                    >
                        {{ section.description }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ section.school.name }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Tab Bar ───────────────────────────────────────────────────── -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Section tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :aria-selected="activeTab === tab.key"
                    :aria-controls="`panel-${tab.key}`"
                    role="tab"
                    @click="switchTab(tab.key)"
                    class="
                        flex items-center gap-2 px-4 py-3 text-sm font-medium
                        whitespace-nowrap border-b-2 transition-colors duration-150
                        focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                    "
                    :class="activeTab === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                    "
                >
                    <i :class="tab.icon" aria-hidden="true" />
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- ── Tab Panels ────────────────────────────────────────────────── -->

        <!-- Class Levels tab -->
        <div
            v-if="activeTab === 'class-levels'"
            :id="`panel-class-levels`"
            role="tabpanel"
            aria-labelledby="tab-class-levels"
        >
            <ClassLevelsTab :section="section" />
        </div>

        <!-- Staff tab — stub for future module -->
        <div
            v-else-if="activeTab === 'staff'"
            :id="`panel-staff`"
            role="tabpanel"
            aria-labelledby="tab-staff"
        >
            <div class="flex flex-col items-center justify-center py-24 text-gray-400 dark:text-gray-600">
                <i class="pi pi-users text-5xl mb-4 opacity-30" aria-hidden="true" />
                <p class="text-lg font-medium">Staff management coming soon</p>
                <p class="text-sm mt-1">This tab will list and manage staff assigned to this section.</p>
            </div>
        </div>

        <!-- Settings tab — stub for future module -->
        <div
            v-else-if="activeTab === 'settings'"
            :id="`panel-settings`"
            role="tabpanel"
            aria-labelledby="tab-settings"
        >
            <div class="flex flex-col items-center justify-center py-24 text-gray-400 dark:text-gray-600">
                <i class="pi pi-cog text-5xl mb-4 opacity-30" aria-hidden="true" />
                <p class="text-lg font-medium">Section settings coming soon</p>
                <p class="text-sm mt-1">Configure section-specific options here.</p>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
