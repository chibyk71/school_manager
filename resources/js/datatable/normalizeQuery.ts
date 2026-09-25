import type { DataTableFilter, DataTableFilters, DataTableQuery, DataTableSort } from './types'
import { DEFAULT_PER_PAGE, MAX_PER_PAGE } from './types'

/** Deterministic normalization so equivalent queries share query keys. */
export function normalizeQuery(input: Partial<DataTableQuery> | null | undefined): DataTableQuery {
    const page = Math.max(1, Number(input?.page) || 1)
    let perPage = Number(input?.perPage)
    if (!Number.isFinite(perPage) || perPage < 1) {
        perPage = DEFAULT_PER_PAGE
    }
    perPage = Math.min(Math.floor(perPage), MAX_PER_PAGE)

    const searchRaw = input?.search
    const search =
        searchRaw === null || searchRaw === undefined || String(searchRaw).trim() === ''
            ? undefined
            : String(searchRaw).trim()

    const filters = normalizeFilters(input?.filters)
    const sorts = normalizeSorts(input?.sorts)

    return {
        page,
        perPage,
        ...(search !== undefined ? { search } : {}),
        ...(filters ? { filters } : {}),
        ...(sorts.length > 0 ? { sorts } : {}),
    }
}

export function normalizeFilters(filters?: DataTableFilters | null): DataTableFilters | undefined {
    if (!filters?.conditions?.length) {
        return undefined
    }
    const conditions: DataTableFilter[] = filters.conditions
        .filter((c) => c && typeof c.field === 'string' && c.field !== '')
        .map((c) => ({
            field: c.field,
            operator: c.operator,
            value: c.value,
        }))
        .sort((a, b) => {
            const fa = `${a.field}:${a.operator}`
            const fb = `${b.field}:${b.operator}`
            return fa < fb ? -1 : fa > fb ? 1 : 0
        })
    return conditions.length ? { conditions } : undefined
}

export function normalizeSorts(sorts?: DataTableSort[] | null): DataTableSort[] {
    if (!sorts?.length) {
        return []
    }
    const seen = new Set<string>()
    const result: DataTableSort[] = []
    for (const s of sorts) {
        if (!s?.field || seen.has(s.field)) continue
        seen.add(s.field)
        result.push({
            field: s.field,
            direction: s.direction === 'desc' ? 'desc' : 'asc',
        })
    }
    return result
}

/** Identity without page — for cache grouping / page-reset decisions. */
export function queryIdentityWithoutPage(query: DataTableQuery): Omit<DataTableQuery, 'page'> {
    const { page: _page, ...rest } = normalizeQuery(query)
    return rest
}
