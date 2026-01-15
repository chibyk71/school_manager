import { InputTypeHTMLAttribute } from "vue";
import type { AcademicSession } from "./academic";
import type { Address } from "./address";

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    schools: [{ id: string, name: string }]
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    /**
     * Authentication & RBAC data shared globally via HandleInertiaRequests
     */
    auth: {
        user: User | null

        /**
         * All permissions the user has (direct + inherited from roles)
         * e.g., ['view-users', 'edit-users', 'delete-users', 'manage-students.*']
         */
        permissions: string[]

        /**
         * Role names the user belongs to
         * e.g., ['admin', 'teacher', 'accountant']
         */
        roles: string[]
    }

    /**
     * Flash messages from Laravel session
     */
    flash: {
        success?: string | null
        error?: string | null
        info?: string | null
        warning?: string | null
    }

    /**
     * Dark mode toggle state (if you're using it)
     */
    darkMode: boolean

    /**
     * Current active school context (multi-tenant)
     */
    school?: {
        id: string
        name: string
        logo?: string | null // renamed from 'image' → 'logo' for clarity (optional)
    } | null;

    currentSession?: AcademicSession
}

// resources/js/types.ts
export interface StatisticData {
    /** Main number to display */
    value: number;
    /** Title (e.g. "Total Students") */
    title: string;
    /** Background image for avatar */
    image: string;
    /** Tailwind background class (e.g. "bg-red-200/50") */
    severity: string;
    /** Growth change */
    growth: number;
    /** Active count */
    active: number;
    /** Inactive count */
    inactive: number;
}

export interface Student {
    [string]: unknown;
    id: number;
    first_name: string;
    last_name: string;
    middle_name: string;
    enrolment_number: string;
    email: string;
    email_verified_at?: string;
    phone: string;
    address: Address;
    created_at: string;
    updated_at: string;
};

/**
 * Represents a single menu item (leaf node or parent with submenu)
 */
export interface MenuItem {
    /** Display title */
    title: string;

    /** Tabler icon class (e.g., "ti ti-layout-dashboard") */
    icon?: string;

    /** Direct route – if present, this is a clickable link */
    link?: string;

    /** Optional badge (e.g., version number) */
    badge?: string;

    /** Nested submenu – if present, this item opens a dropdown */
    submenu?: MenuItem[];
}

/**
 * A menu section/group that appears in the sidebar
 */
export interface MenuSection {
    /** Header text shown above the group */
    header: string;

    /** List of menu items under this header */
    items: MenuItem[];
}

/**
 * Complete sidebar menu structure
 */
export type SidebarMenu = MenuSection[];

export type MenuItemWithChildren = MenuItem & { submenu: MenuItem[] };
export type MenuItemLeaf = MenuItem & { link: string; submenu?: never };

export type MenuItemAll = MenuItemWithChildren | MenuItemLeaf;
