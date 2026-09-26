import type { DataTableQuery } from './types'
import { normalizeQuery } from './normalizeQuery'

/**
 * Approved query identity:
 * ['datatable', resource, { search, filters, sorts, perPage }, page]
 */
export function getDataTableQueryKey(resource: string, query: Partial<DataTableQuery>) {
    const q = normalizeQuery(query)
    return [
        'datatable',
        resource,
        {
            search: q.search ?? null,
            filters: q.filters ?? null,
            sorts: q.sorts ?? null,
            perPage: q.perPage,
        },
        q.page,
    ] as const
}
