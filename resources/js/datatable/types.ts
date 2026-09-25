/**
 * Canonical DataTable query/response contracts (Phase 5).
 * Presentation concerns (PrimeVue) stay in adapters — never here.
 */

export type DataTableFilterOperator =
    | 'equals'
    | 'notEquals'
    | 'contains'
    | 'notContains'
    | 'startsWith'
    | 'endsWith'
    | 'lessThan'
    | 'lessThanOrEqual'
    | 'greaterThan'
    | 'greaterThanOrEqual'
    | 'in'
    | 'notIn'
    | 'between'
    | 'notBetween'
    | 'isNull'
    | 'isNotNull'

export interface DataTableFilter {
    field: string
    operator: DataTableFilterOperator
    value: unknown
}

export interface DataTableFilters {
    conditions: DataTableFilter[]
}

export interface DataTableSort {
    field: string
    direction: 'asc' | 'desc'
}

export interface DataTableQuery {
    page: number
    perPage: number
    search?: string
    filters?: DataTableFilters
    sorts?: DataTableSort[]
}

export interface DataTableMeta {
    currentPage: number
    perPage: number
    total: number
    lastPage: number
}

/** Backend column capability + presentation metadata */
export interface DataTableColumnMeta {
    field: string
    header?: string
    sortable?: boolean
    filterable?: boolean
    searchable?: boolean
    exportable?: boolean
    filterType?: string
    filterOptions?: Array<{ label: string; value: unknown }>
    filterMatchMode?: string
    filterPlaceholder?: string
    hidden?: boolean
    defaultHidden?: boolean
    headerClass?: string
    bodyClass?: string
    width?: string | null
    relation?: string | null
    relatedField?: string | null
    [key: string]: unknown
}

export interface DataTableResponse<T = Record<string, unknown>> {
    data: T[]
    columns: DataTableColumnMeta[]
    meta: DataTableMeta
}

export const DEFAULT_PER_PAGE = 50
export const MAX_PER_PAGE = 100
export const DEFAULT_PREFETCH_PAGES = 2
