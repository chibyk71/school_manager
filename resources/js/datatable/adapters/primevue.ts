/**
 * PrimeVue → canonical DataTable adapters.
 * Keep PrimeVue concerns out of the domain/datasource layer.
 */

import type { DataTableFilter, DataTableFilterOperator, DataTableFilters, DataTableSort } from '../types'

/** PrimeVue DataTable sort field + order (1 | -1 | 0 | null) */
export function primeVueSortToCanonical(
    sortField: string | ((item: any) => string) | undefined | null,
    sortOrder: number | null | undefined,
    multiSortMeta?: Array<{ field: string; order: number | null }> | null,
): DataTableSort[] {
    if (multiSortMeta?.length) {
        return multiSortMeta
            .filter((m) => m.field && (m.order === 1 || m.order === -1))
            .map((m) => ({
                field: m.field,
                direction: (m.order === -1 ? 'desc' : 'asc') as 'asc' | 'desc',
            }))
    }
    if (typeof sortField === 'string' && sortField && (sortOrder === 1 || sortOrder === -1)) {
        return [{ field: sortField, direction: sortOrder === -1 ? 'desc' : 'asc' }]
    }
    return []
}

const MATCH_MODE_MAP: Record<string, DataTableFilterOperator> = {
    equals: 'equals',
    notEquals: 'notEquals',
    contains: 'contains',
    notContains: 'notContains',
    startsWith: 'startsWith',
    endsWith: 'endsWith',
    lt: 'lessThan',
    lte: 'lessThanOrEqual',
    gt: 'greaterThan',
    gte: 'greaterThanOrEqual',
    in: 'in',
    between: 'between',
    dateIs: 'equals',
    dateIsNot: 'notEquals',
    dateBefore: 'lessThan',
    dateAfter: 'greaterThan',
}

export function isSupportedPrimeVueMatchMode(matchMode: string | undefined | null): boolean {
    if (!matchMode) return false
    return Object.prototype.hasOwnProperty.call(MATCH_MODE_MAP, matchMode)
}

/**
 * Map a PrimeVue matchMode to a canonical operator.
 * Returns null for unsupported modes (never silently becomes contains).
 */
export function mapPrimeVueMatchMode(matchMode: string | undefined | null): DataTableFilterOperator | null {
    if (!matchMode) return null
    return MATCH_MODE_MAP[matchMode] ?? null
}

/**
 * PrimeVue filters object: { field: { value, matchMode }, global?: ... }
 * Global search is handled separately (search string).
 * Unsupported match modes are skipped (not coerced to contains).
 */
export function primeVueFiltersToCanonical(
    filters: Record<string, { value?: unknown; matchMode?: string } | undefined> | null | undefined,
): DataTableFilters | undefined {
    if (!filters) return undefined
    const conditions: DataTableFilter[] = []

    for (const [field, spec] of Object.entries(filters)) {
        if (field === 'global' || !spec) continue
        const value = spec.value
        if (value === null || value === undefined || value === '') continue
        if (Array.isArray(value) && value.length === 0) continue

        const matchMode = spec.matchMode ?? 'contains'
        const operator = mapPrimeVueMatchMode(matchMode)
        if (operator === null) {
            // Unsupported mode — skip rather than invent semantics
            continue
        }

        conditions.push({ field, operator, value })
    }

    return conditions.length ? { conditions } : undefined
}

export function extractGlobalSearch(
    filters: Record<string, { value?: unknown; matchMode?: string } | undefined> | null | undefined,
): string | undefined {
    const raw = filters?.global?.value
    if (raw === null || raw === undefined) return undefined
    const s = String(raw).trim()
    return s === '' ? undefined : s
}
