import axios from 'axios'
import qs from 'qs'
import type { QueryClient } from '@tanstack/vue-query'
import type { DataTableQuery, DataTableResponse } from './types'
import { normalizeQuery } from './normalizeQuery'
import { getDataTableQueryKey } from './queryKey'
import { DEFAULT_PREFETCH_PAGES } from './types'

export interface DataTableFetchOptions {
    /** Extra static query params (resource-specific, e.g. school_section_id) */
    extraParams?: Record<string, unknown>
}

/**
 * Transport + query-key + prefetch layer.
 * No Vue state, toasts, selection, or custom cache.
 */
export const DataTableDataSource = {
    normalizeQuery,

    getQueryKey(resource: string, query: Partial<DataTableQuery>) {
        return getDataTableQueryKey(resource, query)
    },

    /**
     * Serialize canonical query for the Laravel DataTable API.
     * Uses semantic operators — not Purity $eq syntax.
     */
    serializeParams(query: DataTableQuery, extraParams?: Record<string, unknown>): Record<string, unknown> {
        const q = normalizeQuery(query)
        const params: Record<string, unknown> = {
            page: q.page,
            perPage: q.perPage,
            ...extraParams,
        }
        if (q.search) {
            params.search = q.search
        }
        if (q.filters?.conditions?.length) {
            params.filters = { conditions: q.filters.conditions }
        }
        if (q.sorts?.length) {
            params.sorts = q.sorts.map((s) => ({
                field: s.field,
                direction: s.direction,
            }))
        }
        return params
    },

    async fetch<T = Record<string, unknown>>(
        resource: string,
        query: Partial<DataTableQuery>,
        options: DataTableFetchOptions = {},
    ): Promise<DataTableResponse<T>> {
        const q = normalizeQuery(query)
        const params = this.serializeParams(q, options.extraParams)
        const queryString = qs.stringify(params, {
            arrayFormat: 'indices',
            encode: true,
            skipNulls: true,
        })
        const url = queryString ? `${resource}?${queryString}` : resource
        const { data } = await axios.get(url)
        return mapResponse<T>(data)
    },

    async prefetch<T = Record<string, unknown>>(
        queryClient: QueryClient,
        resource: string,
        query: Partial<DataTableQuery>,
        options: DataTableFetchOptions & { pages?: number } = {},
    ): Promise<void> {
        const q = normalizeQuery(query)
        const pagesAhead = options.pages ?? DEFAULT_PREFETCH_PAGES
        const tasks: Promise<unknown>[] = []

        for (let i = 1; i <= pagesAhead; i++) {
            const nextPage = q.page + i
            const nextQuery = { ...q, page: nextPage }
            const key = getDataTableQueryKey(resource, nextQuery)
            tasks.push(
                queryClient.prefetchQuery({
                    queryKey: key as unknown as unknown[],
                    queryFn: () => this.fetch<T>(resource, nextQuery, options),
                }).catch(() => {
                    /* prefetch failures must not affect the active query */
                }),
            )
        }

        await Promise.all(tasks)
    },
}

/** Map backend (canonical + temporary legacy keys) → frontend DataTableResponse */
export function mapResponse<T>(raw: any): DataTableResponse<T> {
    const meta = raw?.meta ?? {}
    return {
        data: Array.isArray(raw?.data) ? raw.data : [],
        columns: Array.isArray(raw?.columns) ? raw.columns : [],
        meta: {
            currentPage: Number(meta.currentPage ?? raw?.currentPage ?? 1) || 1,
            perPage: Number(meta.perPage ?? raw?.perPage ?? 50) || 50,
            total: Number(meta.total ?? raw?.totalRecords ?? 0) || 0,
            lastPage: Number(meta.lastPage ?? raw?.lastPage ?? 1) || 1,
        },
    }
}
