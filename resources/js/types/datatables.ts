// src/types/datatables.ts
/**
 * @file Core type definitions for the AdvancedDataTable system
 * @description Strongly typed, fully documented, and future-proof types used across
 *              the entire reusable DataTable ecosystem (Vue 3 + PrimeVue + Inertia + Laravel)
 * @author Your Name <you@example.com>
 * @version 2.0.0
 */

import type { DefineComponent, FunctionalComponent, VNode, VNodeChild } from 'vue'
import { FilterMatchMode } from '@primevue/core/api'

/**
 * Re-export PrimeVue's filter match modes for convenience and consistency
 */
export { FilterMatchMode as FilterModes }

/**
 * Supported column filter input types
 */
export type ColumnFilterType =
    | 'text'
    | 'number'
    | 'date'
    | 'boolean'
    | 'dropdown'
    | 'multiselect'

/**
 * Simplified declarative template syntax for custom cell rendering.
 * Allows developers to write HTML-like structures without importing `h()`.
 *
 * @example
 * { template: 'div', class: 'flex items-center gap-2', children: [
 *   { template: 'img', src: row.avatar, class: 'w-8 h-8 rounded-full' },
 *   { template: 'span', text: row.name }
 * ]}
 */
export type TemplateNode =
    | string
    | {
        template: string
        text?: string | ((row: any) => string)
        src?: string | ((row: any) => string)
        class?: string | string[] | Record<string, boolean> | ((row: any) => any)
        style?: string | Record<string, string> | ((row: any) => any)
        props?: Record<string, any>
        on?: Record<string, ((...args: any[]) => any)>
        children?: TemplateNode[]
    }

/**
 * Component-based renderer – ideal for reusable Vue components like badges, toggles, etc.
 */
export interface ComponentRenderer<T = any> {
    component: DefineComponent<any> | FunctionalComponent | string
    props?: Record<string, any> | ((row: T) => Record<string, any>)
    on?: Record<string, ((...args: any[]) => any)>
    slots?: Record<string, () => VNodeChild>
}

/**
 * All possible values a column's `render` function can return.
 * Narrowed union to improve type inference and prevent "any" leakage.
 */
export type CellRenderer<T = any> =
    | string
    | number
    | boolean
    | null
    | undefined
    | VNode
    | VNode[]
    | TemplateNode
    | TemplateNode[]
    | ComponentRenderer<T>
    | ((row: T) => VNode | VNode[] | TemplateNode | string | number | null | undefined)

/**
 * Main column definition – the heart of the entire table system.
 *
 * All properties are optional except `field` and `header` (enforced at runtime via helper).
 */
export interface ColumnDefinition<T = Record<string, unknown>> {
    /** Unique field identifier – supports dot notation (e.g., 'user.profile.name') */
    field: keyof T | (string & {})

    /** Header label – string or dynamic function */
    header: string

    /** Enable sorting on this column */
    sortable?: boolean

    /** Custom comparator for complex sorting logic */
    sortFunction?: (a: T, b: T, order: 1 | -1) => number

    /** Enable filtering UI */
    filterable?: boolean

    /** Type of filter input to render */
    filterType?: ColumnFilterType

    /** For dropdown/multiselect – static or dynamic options */
    filterOptions?: { label: string; value: any; disabled?: boolean }[]

    /** PrimeVue filter match mode */
    filterMatchMode?: keyof typeof FilterMatchMode

    /** Input placeholder */
    filterPlaceholder?: string

    /** Custom cell renderer – most powerful option */
    render?: (row: T) => CellRenderer<T>

    /** Simple value formatter – used when no full render is needed */
    formatter?: (value: any, row: T) => string | number | boolean | null | undefined

    /** Hide from visibility toggle menu */
    hidden?: boolean

    /** Cannot be hidden by user */
    frozen?: boolean

    /** Text alignment */
    align?: 'left' | 'center' | 'right'

    /** Header styling */
    headerClass?: string | ((col: ColumnDefinition<T>) => string)

    /** Body cell styling */
    bodyClass?: string | ((row: T) => string)

    /** Fixed column width */
    width?: string

    minWidth?: string
    maxWidth?: string

    /** Resizable & reorderable */
    resizable?: boolean
    reorderable?: boolean

    /** Multi-header grouping */
    colGroup?: string
    colSpan?: number
    rowSpan?: number

    /** Export control */
    exportable?: boolean
    exportFormatter?: (value: any, row: T) => string

    /** Accessibility */
    ariaLabel?: string

    /** Arbitrary metadata – useful for plugins or internal flags */
    meta?: Record<string, any>
}

/**
 * Comprehensive filter operator set – matches Laravel Scout / Query Builder style
 */
export type FilterOperator =
    | '$eq' | '$eqc'
    | '$ne'
    | '$lt' | '$lte' | '$gt' | '$gte'
    | '$in' | '$notIn'
    | '$contains' | '$notContains' | '$containsc' | '$notContainsc'
    | '$startsWith' | '$startsWithc'
    | '$endsWith' | '$endsWithc'
    | '$null' | '$notNull'
    | '$between' | '$notBetween'
    | '$or' | '$and'

export interface FilterCondition {
    field: string
    operator: FilterOperator
    value: any
}

export interface FilterGroup {
    operator: '$and' | '$or'
    conditions: Array<FilterCondition | FilterGroup>
}

export type Filter = FilterCondition | FilterGroup
export type Filters = Record<string, Filter>

/**
 * Sorting
 */
export type SortOrder = 'asc' | 'desc'

export interface Sort {
    field: string
    order: SortOrder
}

/**
 * Pagination
 */
export interface Pagination {
    page: number
    pageSize: number
}

/**
 * API Contracts
 */
export interface DataTableRequest<T = Record<string, unknown>> {
    filters?: Filters
    sort?: Sort[]
    pagination?: Pagination
    globalSearch?: string
    columns?: ColumnDefinition<T>[]
    with?: string[] // eager-loaded relations
}

export interface DataTableResponse<T = Record<string, unknown>> {
    data: T[]
    totalRecords: number
    page: number
    pageSize: number
    /** Optional metadata (e.g. aggregations, summaries) */
    meta?: Record<string, any>
}

/**
 * Local table state (for Pinia/localStorage persistence)
 */
export interface DataTableState<T = Record<string, unknown>> {
    request: DataTableRequest<T>
    response: DataTableResponse<T>
}

export interface DataTableColumnState {
    field: string
    visible: boolean
    width?: string
    sortOrder?: SortOrder
    filterValue?: any
}

export interface DataTableStateWithColumns<T = Record<string, unknown>> extends DataTableState<T> {
    columns: DataTableColumnState[]
}

/**
 * Bulk actions configuration
 */
export interface BulkAction {
    /** Display text */
    label: string

    /** PrimeIcons class */
    icon?: string

    /** Button severity */
    severity?: 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' | 'help'

    /** Unique action identifier sent to backend */
    action: string

    /** Optional confirmation dialog */
    confirm?: {
        message: string
        header?: string
        acceptLabel?: string
        rejectLabel?: string
        severity?: 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' | 'help'
    }

    /** Disable when no rows selected */
    disabled?: boolean

    /** Show only when certain conditions are met */
    visible?: (selectedRows: any[]) => boolean
}

/**
 * Central type definition for table row actions used throughout the application.
 * This interface powers the reusable ActionField component and ensures consistent, secure, and professional type-safe action definitions across all DataTables.
 *
 * Features Supported:
 * - Dynamic labels based on row data
 * - Icons and severity styling
 * - Async handlers
 * - Fine-grained visibility and disable controls
 * - Optional confirmation dialogs with customizable appearance
 *
 * This file should be imported wherever table actions are defined.
*/
export interface TableAction<T = any> {
    /** Action label – static string or dynamic function returning string */
    label: string | ((row: T) => string);

    /** PrimeVue icon class (e.g., 'pi pi-eye', 'pi pi-trash') */
    icon?: string;

    /** PrimeVue severity for button/menu item styling */
    severity?: 'secondary' | 'success' | 'info' | 'warning' | 'help' | 'danger';

    /** Handler executed when action is triggered – supports async operations */
    handler: (row: T) => void | Promise<void>;

    /** Control visibility – boolean or callback for row-specific/permission checks */
    show?: boolean | ((row: T) => boolean);

    /** Disable the action (grayed out) – boolean or row-specific callback */
    disabled?: boolean | ((row: T) => boolean);

    /** Require confirmation dialog before executing handler */
    confirm?: {
        message: string;
        header?: string;
        icon?: string;
        acceptClass?: string; // e.g., 'p-button-danger'
    };
}
