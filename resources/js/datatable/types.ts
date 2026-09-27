/**
 * Canonical DataTable query/response contracts (Phase 5 + Phase 6).
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

/**
 * DataTableQuery — describes the current table view (presentation + membership).
 * Pagination and sorts are presentation concerns for the live table.
 */
export interface DataTableQuery {
    page: number
    perPage: number
    search?: string
    filters?: DataTableFilters
    sorts?: DataTableSort[]
}

/**
 * DataTableSelectionQuery — membership only.
 * Must NOT contain page, perPage, pagination cursor, or sorts.
 */
export interface DataTableSelectionQuery {
    search?: string
    filters?: DataTableFilters
}

/**
 * Explicit selection model.
 * - ids: exact records selected (cross-page, preserved independently of current query)
 * - query: all records matching membership at execution time
 */
export type DataTableSelection =
    | {
          type: 'ids'
          ids: Array<string | number>
      }
    | {
          type: 'query'
          query: DataTableSelectionQuery
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

/** Backend-declared bulk action capability (frontend must not invent action ids). */
export interface BulkActionCapability {
    id: string
    label: string
    icon?: string
    requiresConfirmation?: boolean
    /** atomic | partial — informs UI expectations; action defines semantics */
    semantics?: 'atomic' | 'partial'
}

/**
 * Capability metadata returned with DataTable responses.
 * Optional fields preserve backward compatibility with Phase 5 consumers.
 */
export interface DataTableCapabilities {
    bulkActions?: BulkActionCapability[]
    exportable?: boolean
    maxSelectionIds?: number
}

export interface DataTableResponse<T = Record<string, unknown>> {
    data: T[]
    columns: DataTableColumnMeta[]
    meta: DataTableMeta
    /** Phase 6 optional capability surface */
    capabilities?: DataTableCapabilities
}

/** Canonical bulk-action request wire contract */
export interface DataTableBulkActionRequest {
    selection: DataTableSelection
    action: string
    payload?: unknown
}

export interface BulkActionError {
    id?: string | number
    message: string
    code?: string
}

/** Normalized bulk-action result */
export interface BulkActionResult {
    processed: number
    succeeded: number
    failed: number
    skipped: number
    errors?: BulkActionError[]
    message?: string
    meta?: Record<string, unknown>
}

export type DataTableExportTarget =
    | { type: 'page' }
    | { type: 'ids'; ids: Array<string | number> }
    | { type: 'query'; query: DataTableSelectionQuery }

export type ExportFormat = 'csv' | 'xlsx'

/** Canonical export request wire contract */
export interface DataTableExportRequest {
    query: DataTableQuery
    target: DataTableExportTarget
    columns: string[]
    format: ExportFormat
}

export const DEFAULT_PER_PAGE = 50
export const MAX_PER_PAGE = 100
export const DEFAULT_PREFETCH_PAGES = 2
export const DEFAULT_MAX_SELECTION_IDS = 5000
