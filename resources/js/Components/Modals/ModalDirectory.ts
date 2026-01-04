// src/components/Modals/ModalDirectory.ts
/**
 * ModalDirectory.ts
 *
 * Central, type-safe registry for all dynamic modals in the application.
 *
 * Features / Problems Solved:
 * - Acts as the single source of truth for modal identifiers, lazy-loaded components, and optional UI configurations.
 * - Enables full code-splitting via dynamic imports → smaller initial bundle.
 * - Provides optional per-modal settings (title, maxWidth, maxHeight, persistent behavior) applied automatically in ResourceDialog.vue.
 * - Generates a strict `ModalId` union type from the object keys → eliminates typos and offers autocomplete when opening modals.
 * - Offers `ModalConfig<Id>` utility type for type-safe access to a specific modal's configuration.
 * - Easy extensibility: simply add a new entry with a unique key.
 *
 * Fits into the Modal Module:
 * - Imported by ModalService.ts for ID validation and config retrieval.
 * - Used by ResourceDialog.vue to resolve and render the correct async component.
 * - Indirectly consumed via useModal().open() / prepend() throughout the app.
 */

import { Component } from 'vue';

/**
 * Shape of a single modal registration entry.
 */
export interface ModalRegistration {
    /**
     * Lazy loader returning the modal Vue component.
     * Uses dynamic import for optimal code-splitting.
     */
    loader: () => Promise<Component>;

    /**
     * Optional configuration applied by ResourceDialog.vue.
     */
    config?: {
        /** Header title displayed in the PrimeVue Dialog */
        title?: string;

        /**
         * Tailwind max-width utility class.
         * Recommended values: 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', 'full'
         */
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | 'full';

        /** Custom maximum height (e.g., '80vh', '600px') */
        maxHeight?: string;

        /**
         * When true, disables ESC key and overlay click closing.
         * Useful for critical confirmation dialogs.
         */
        persistent?: boolean;

        // Future extensions (icon, custom transition, etc.) can be added here
    };
}

/**
 * Registry containing all available modals.
 *
 * Keys are unique identifiers used across the app.
 * Values define the component loader and optional configuration.
 */
export const ModalComponentDirectory: Record<string, ModalRegistration> = {
    'custom-field': {
        loader: () => import('@/Components/Modals/Create/CustomFields.vue'),
        config: {
            title: 'Custom Field',
            maxWidth: 'md',
        },
    },

    'assign-teacher-subject': {
        loader: () => import('@/Components/Modals/Create/AssignTeacherSubjectClass.vue'),
    },

    'add-staff': {
        loader: () => import('@/Components/Modals/Create/AddStaff.vue'),
    },

    'assign-department-role': {
        loader: () => import('@/Components/Modals/Create/AssignRoleDepartmentModal.vue'),
    },

    'admin-password-reset': {
        loader: () => import('@/Components/Modals/Create/AdminPasswordResetModal.vue'),
    },

    'create-role': {
        loader: () => import('@/Components/Modals/Create/Roles/CreateModal.vue'),
    },

    'department': {
        loader: () => import('@/Components/Modals/Create/hrm/DepartmentFormModal.vue'),
        config: {
            title: 'Department',
            maxWidth: 'lg',
        },
    },

    'department-details': {
        loader: () => import('@/Components/Modals/Show/DepartmentDetailsModal.vue'),
        config: {
            title: 'Department Details',
            maxWidth: 'xl',
        },
    },

    'address': {
        loader: () => import('@/Components/Modals/Create/AddressModal.vue'),
        config: {
            title: 'Address',
            maxWidth: '2xl',
        },
    },

    'dynamic-enum-metadata': {
        loader: () => import('@/Components/Modals/Create/DynamicEnumMetadataForm.vue'),
        config: {
            title: 'Edit Enum Details',
            maxWidth: 'lg',
        },
    },
} as const;

/**
 * Union type of all registered modal identifiers.
 *
 * Use this type when calling `useModal().open()` or `useModal().prepend()`
 * to benefit from autocomplete and prevent invalid IDs.
 *
 * @example
 * const modal = useModal();
 * modal.open('custom-field' as ModalId, data);
 */
export type ModalId = keyof typeof ModalComponentDirectory;

/**
 * Utility type extracting the configuration type for a specific modal.
 *
 * Returns `undefined` if the modal has no config defined.
 *
 * @template Id - A valid ModalId
 *
 * @example
 * type CustomFieldConfig = ModalConfig<'custom-field'>;
 * // => { title?: string; maxWidth?: string; ... } | undefined
 */
export type ModalConfig<Id extends ModalId> =
    typeof ModalComponentDirectory[Id]['config'];
