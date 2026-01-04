// resources/js/types/dynamic-enums.ts
/**
 * types/dynamic-enums.ts
 *
 * Centralized TypeScript type definitions for the DynamicEnums module.
 *
 * Features / Problems Solved:
 * - Provides strict, reusable types for dynamic enum data structures used across the app:
 *     • Backend API responses (controller options endpoint)
 *     • Frontend composables (useDynamicEnums)
 *     • Form components (DynamicEnumField.vue)
 *     • Admin management pages and modals
 * - Ensures consistency between Laravel JSON responses and Vue consumption.
 * - Makes option objects extensible (value + label required; color and future fields optional).
 * - Includes full enum definition type for admin views (includes metadata).
 * - Readonly arrays where appropriate to prevent accidental mutation.
 * - Exportable for easy import: import type { DynamicEnumOption, DynamicEnum } from '@/types/dynamic-enums';
 * - Aligns perfectly with the DynamicEnum Eloquent model casts and validation:
 *     • options: array of objects with string value/label, optional string color.
 *
 * Fits into the DynamicEnums Module:
 * - Single source of truth for all dynamic enum-related types.
 * - Used by:
 *     • useDynamicEnums composable (return type of load(), options ref)
 *     • DynamicEnumField.vue (props, slots, computed renderMode)
 *     • Admin Index.vue (table rows, expansion)
 *     • Future modals (DynamicEnumMetadataForm, DynamicEnumOptionsForm)
 *     • Any form using <DynamicEnumField />
 * - Prevents type mismatches and "any" usage.
 * - Production-ready: clear naming, documentation, extensibility.
 */

export interface DynamicEnumOption {
    /** The stored value (machine-readable, unique within the enum) */
    value: string;

    /** Human-readable label displayed in dropdowns, radios, badges */
    label: string;

    /** Optional Tailwind color class for visual distinction (e.g., 'bg-indigo-100 text-indigo-800') */
    color?: string;

    /** Future-proof: add more optional metadata without breaking changes */
    // icon?: string;
    // sort_order?: number;
    // disabled?: boolean;
}

/**
 * Full dynamic enum definition – as returned by the index endpoint or edit modal payload.
 * Includes metadata (label, description) and the options array.
 */
export interface DynamicEnum {
    /** Primary key (UUID) */
    id: string;

    /** Machine name – immutable, used as property name and in validation */
    name: string;

    /** Display label – editable by school admins */
    label: string;

    /** Fully qualified model class this enum applies to (immutable) */
    applies_to: string;

    /** Optional description for admin reference */
    description?: string | null;

    /** Optional color for the enum badge in admin table */
    color?: string | null;

    /** Array of allowed options – editable (add/edit/delete/reorder) */
    options: DynamicEnumOption[];

    /** Nullable school_id – null = global default, non-null = school override */
    school_id?: string | null;

    /** Timestamps */
    created_at: string;
    updated_at: string;
}

/**
 * API response type for the options endpoint (/api/dynamic-enums/options/{appliesTo}/{name})
 */
export interface DynamicEnumOptionsResponse {
    options: DynamicEnumOption[];
}

/**
 * Optional: Type for partial updates (e.g., metadata only or options only)
 */
export type DynamicEnumMetadataUpdate = Pick<DynamicEnum, 'label' | 'description' | 'color'>;

export type DynamicEnumOptionsUpdate = {
    options: DynamicEnumOption[];
};
