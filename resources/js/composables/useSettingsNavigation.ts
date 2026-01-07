// resources/js/composables/useSettingsNavigation.ts
/**
 * useSettingsNavigation.ts v2.0 – Centralized Settings Sidebar Navigation (Icon-Free Version)
 *
 * Features / Problems Solved:
 * - Eliminates duplicated sidebar navigation arrays across all Settings pages
 * - Single source of truth for all settings navigation groups and links
 * - Automatically highlights the active link based on the current Inertia route
 * - Supports top-level groups with sub-items (perfect for expandable sidebars or grouped navigation)
 * - Fully type-safe with clear interfaces for maintainability and IDE support
 * - Zero runtime overhead: uses pure computed properties, no Pinia store or watchers required
 * - Seamless integration with SettingsSidebar.vue (passes items with computed active state)
 * - Future-proof: easy to add permissions, badges, tooltips, or sub-menus later
 * - Icons removed as requested – keeps the structure clean and focused on labels/routes
 *
 * Fits into the Settings Module:
 * - Imported in every Settings page (e.g., Localization.vue, Company.vue, Academic.vue)
 * - Provides ready-to-use navigation arrays for <SettingsSidebar :items="navItems" />
 * - Ensures consistent sidebar appearance and behavior across the entire settings section
 * - Centralizes all route names – prevents typos and makes refactoring safe
 *
 * Usage Example:
 *   const { websiteSettingsNav } = useSettingsNavigation()
 *   // In template:
 *   <SettingsSidebar title="Website Settings" :items="websiteSettingsNav" />
 */

import { computed } from 'vue'
import { route } from 'ziggy-js'

export interface NavItem {
    /** Display label shown in the sidebar */
    label: string
    /** Route href (use Ziggy's route() for type-safety and consistency) */
    href: string
    /** Optional manual override for active state (rarely needed) */
    active?: boolean
}

export interface NavGroup {
    /** Group title displayed above the list of links */
    title: string
    /** Array of navigation items belonging to this group */
    items: NavItem[]
}

/**
 * Central definition of all settings navigation groups.
 * Add, remove, or reorder groups/items here – changes reflect instantly across all settings pages.
 */
const navigationGroups: NavGroup[] = [
    {
        title: 'General Settings',
        items: [

            { label: 'Profile Settings', href: route('settings.general.profile') },
            { label: 'Security Settings', href: route('settings.general.security') },
            { label: 'Notifications', href: route('settings.general.notifications') },
            { label: 'API Keys', href: route('settings.general.api_keys') },
            { label: 'Connected Apps', href: route('settings.general.connected_apps') },
        ],
    },
    {
        title: 'Website & Branding',
        items: [
            { label: 'Company Settings', href: route('settings.website.company') },
            { label: 'Localization', href: route('settings.website.localization') },
            { label: 'Themes & Appearance', href: route('settings.website.themes') },
            { label: 'Prefixes', href: route('settings.website.prefixes') },
            { label: 'Social Authentication', href: route('settings.website.social') },
            { label: 'Web Translations', href: route('settings.website.language') },
        ],
    },
    {
        title: 'App & Customization',
        items: [
            { label: 'Invoice Settings', href: route('settings.system.invoice') },
            { label: 'Custom Fields', href: route('settings.system.fields') },
            { label: 'Printer Settings', href: route('settings.system.printer') },
            { label: 'GDPR & Cookies', href: route('settings.system.gdpr') },
            { label: 'User Management', href: route('settings.system.user-management') },
        ],
    },
    {
        title: 'System & Communication',
        items: [
            { label: 'Email Settings', href: route('settings.communication.email') },
            { label: 'Email Templates', href: route('settings.communication.templates') },
            { label: 'SMS Gateways', href: route('settings.communication.sms') },
            { label: 'OTP Settings', href: route('settings.communication.otp') },
        ],
    },
    {
        title: 'Financial Settings',
        items: [
            { label: 'Payment Gateways', href: route('settings.financial.gateways') },
            { label: 'Tax Rates', href: route('settings.financial.taxes') },
            { label: 'Bank Accounts', href: route('settings.financial.banks') },
            { label: 'Fees Settings', href: route('settings.financial.fees') },
        ],
    },
    {
        title: 'Academic Settings',
        items: [
            { label: 'Academic Year', href: route('settings.academic.year') },
            { label: 'Grading Scales', href: route('settings.academic.grading') },
            { label: 'Subjects', href: route('settings.academic.subjects') },
            { label: 'Attendance Rules', href: route('settings.academic.attendance') },
        ],
    },
    {
        title: 'Advanced Settings',
        items: [
            { label: 'Storage', href: route('settings.advanced.storage') },
            { label: 'Backup & Restore', href: route('settings.advanced.backup') },
            { label: 'Ban IP Address', href: route('settings.advanced.ip') },
            { label: 'Maintenance Mode', href: route('settings.advanced.maintenance') },
        ],
    },
]

/**
 * Composable that returns navigation groups with automatically computed active states.
 */
export function useSettingsNavigation() {
    /**
     * Enhance items with active state based on current route.
     * Uses Ziggy's route().current() with wildcard support for nested routes.
     */
    const enhancedGroups = computed<NavGroup[]>(() => {
        return navigationGroups.map((group) => ({
            ...group,
            items: group.items.map((item) => ({
                ...item,
                active: item.active ?? route().current(item.href.split('?')[0] + '*'),
            })),
        }))
    })

    /**
     * Helper to retrieve a specific group's items by title.
     */
    const getGroupByTitle = (title: string): NavItem[] => {
        const group = enhancedGroups.value.find((g) => g.title === title)
        return group?.items ?? []
    }

    return {
        /** All navigation groups (title + items) */
        allGroups: enhancedGroups,

        /** Direct access to commonly used groups */
        generalSettingsNav: computed(() => getGroupByTitle('General Settings')),
        websiteSettingsNav: computed(() => getGroupByTitle('Website & Branding')),
        appSettingsNav: computed(() => getGroupByTitle('App & Customization')),
        systemSettingsNav: computed(() => getGroupByTitle('System & Communication')),
        financialSettingsNav: computed(() => getGroupByTitle('Financial Settings')),
        academicSettingsNav: computed(() => getGroupByTitle('Academic Settings')),
        advancedSettingsNav: computed(() => getGroupByTitle('Advanced Settings')),

        /** Generic utility for any group */
        getGroupByTitle,
    }
}
