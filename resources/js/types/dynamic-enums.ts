/**
 * Canonical Dynamic Enum TypeScript contracts (Phase 7).
 *
 * Backend-aligned models for:
 *   - Consumer options API: GET /dynamic-enums/{key}/options
 *   - Administration detail: Settings/System/DynamicEnums/Show
 *   - Catalogue rows: Settings/System/DynamicEnums/Index
 *
 * Stale JSON/Config-era fields (name, applies_to, school_id on definitions as
 * primary identity, bulk options arrays) are intentionally absent.
 */

/** Selectable option as returned by the consumer options endpoint. */
export interface DynamicEnumEffectiveOption {
    value: string;
    label: string;
    is_active?: boolean;
    color?: string | null;
    icon?: string | null;
}

/**
 * Alias for consumer/form usage. Prefer DynamicEnumEffectiveOption for clarity;
 * this name is retained for existing import sites (subject.ts, etc.).
 */
export type DynamicEnumOption = DynamicEnumEffectiveOption;

/** Application-owned definition row (catalogue / index). */
export interface DynamicEnumDefinition {
    id: string;
    key: string;
    label: string;
    description: string | null;
}

/** Per-option capability hints from the administration detail payload. */
export interface DynamicEnumOptionCapabilities {
    can_edit_tenant?: boolean;
    can_activate_tenant?: boolean;
    can_deactivate_tenant?: boolean;
    can_delete_tenant?: boolean;
    can_edit_school?: boolean;
    can_activate_school?: boolean;
    can_deactivate_school?: boolean;
    can_delete_school?: boolean;
    can_edit?: boolean;
    can_activate?: boolean;
    can_deactivate?: boolean;
    can_delete?: boolean;
    can_override?: boolean;
    can_reset?: boolean;
    can_make_required?: boolean;
    can_remove_required?: boolean;
}

/** Effective option row in the administration Show detail. */
export interface DynamicEnumDetailOption {
    value: string;
    label: string;
    is_active: boolean;
    is_required: boolean;
    sort_order: number;
    color: string | null;
    icon: string | null;
    source: string;
    overridden: boolean;
    enforced: boolean;
    enforcement_reason?: string | null;
    tenant_label: string | null;
    tenant_sort_order: number | null;
    tenant_color: string | null;
    tenant_icon: string | null;
    school_label: string | null;
    school_sort_order: number | null;
    school_color: string | null;
    school_icon: string | null;
    tenant_option_id: string | null;
    school_option_id: string | null;
    capabilities: DynamicEnumOptionCapabilities;
}

/** Definition-level capability hints. */
export interface DynamicEnumDetailCapabilities {
    can_edit_definition?: boolean;
    can_manage_tenant_options?: boolean;
    can_manage_school_options?: boolean;
    can_make_required?: boolean;
}

/** Administration Show payload. */
export interface DynamicEnumDetail {
    key: string;
    label: string;
    description: string | null;
    definition_id?: string;
    scope: 'tenant' | 'school' | string;
    school_id?: string | null;
    options: DynamicEnumDetailOption[];
    capabilities: DynamicEnumDetailCapabilities;
}

/** Consumer options API response shape. */
export interface DynamicEnumOptionsResponse {
    key?: string;
    label?: string;
    options: DynamicEnumEffectiveOption[];
    message?: string;
}


/** Effective school catalogue row (index in school context). */
export interface DynamicEnumCatalogueOption {
    value: string;
    label: string;
    is_active: boolean;
    is_required: boolean;
    sort_order?: number;
    color?: string | null;
    icon?: string | null;
    source: 'inherited' | 'overridden' | 'school-created' | string;
}

export interface DynamicEnumEffectiveCatalogueRow {
    id: string;
    key: string;
    label: string;
    description: string | null;
    options: DynamicEnumCatalogueOption[];
    option_count?: number;
}
