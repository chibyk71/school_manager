import { computed, ref, watch, type Ref } from 'vue'
import {
    useQuery,
    useQueryClient,
    keepPreviousData,
} from '@tanstack/vue-query'
import type { DataTableQuery, DataTableResponse, DataTableFilters, DataTableSort } from './types'
import { DEFAULT_PER_PAGE, DEFAULT_PREFETCH_PAGES } from './types'
import { DataTableDataSource } from './DataTableDataSource'
import { getDataTableQueryKey } from './queryKey'
import { normalizeQuery } from './normalizeQuery'

export interface UseDataTableQueryOptions {
    resource: string
    initialQuery?: Partial<DataTableQuery>
    extraParams?: Record<string, unknown> | Ref<Record<string, unknown>>
    /** Prefetch N pages ahead (default 2). Set 0 to disable. */
    prefetchPages?: number
    enabled?: boolean | Ref<boolean>
}

export function useDataTableQuery<T = Record<string, unknown>>(options: UseDataTableQueryOptions) {
    const queryClient = useQueryClient()
    const prefetchPages = options.prefetchPages ?? DEFAULT_PREFETCH_PAGES

    const queryState = ref<DataTableQuery>(
        normalizeQuery({
            page: 1,
            perPage: DEFAULT_PER_PAGE,
            ...options.initialQuery,
        }),
    )

    const resource = computed(() => options.resource)
    const extraParams = computed(() => {
        const p = options.extraParams
        if (!p) return undefined
        return 'value' in (p as object) ? (p as Ref<Record<string, unknown>>).value : (p as Record<string, unknown>)
    })
    const enabled = computed(() => {
        const e = options.enabled
        if (e === undefined) return true
        return typeof e === 'boolean' ? e : e.value
    })

    const queryKey = computed(() =>
        getDataTableQueryKey(resource.value, queryState.value) as unknown as unknown[],
    )

    const queryResult = useQuery<DataTableResponse<T>, Error>({
        queryKey,
        queryFn: () =>
            DataTableDataSource.fetch<T>(resource.value, queryState.value, {
                extraParams: extraParams.value,
            }),
        enabled,
        placeholderData: keepPreviousData,
    })

    // Prefetch upcoming pages when active data settles
    watch(
        () => [queryResult.data.value, queryResult.isFetching.value, queryState.value.page] as const,
        ([data, isFetching]) => {
            if (isFetching || !data || prefetchPages <= 0) return
            const lastPage = data.meta.lastPage
            const current = queryState.value.page
            if (current >= lastPage) return

            void DataTableDataSource.prefetch(queryClient, resource.value, queryState.value, {
                extraParams: extraParams.value,
                pages: Math.min(prefetchPages, Math.max(0, lastPage - current)),
            })
        },
        { flush: 'post' },
    )

    function setPage(page: number) {
        queryState.value = normalizeQuery({ ...queryState.value, page })
    }

    function setPerPage(perPage: number) {
        queryState.value = normalizeQuery({ ...queryState.value, perPage, page: 1 })
    }

    function setSearch(search: string | undefined) {
        queryState.value = normalizeQuery({
            ...queryState.value,
            search: search?.trim() || undefined,
            page: 1,
        })
    }

    function setFilters(filters: DataTableFilters | undefined) {
        queryState.value = normalizeQuery({ ...queryState.value, filters, page: 1 })
    }

    function setSorts(sorts: DataTableSort[] | undefined) {
        queryState.value = normalizeQuery({ ...queryState.value, sorts, page: 1 })
    }

    function refresh() {
        return queryClient.invalidateQueries({
            queryKey: ['datatable', resource.value],
        })
    }

    function prefetch(pages?: number) {
        return DataTableDataSource.prefetch(queryClient, resource.value, queryState.value, {
            extraParams: extraParams.value,
            pages: pages ?? prefetchPages,
        })
    }

    const rows = computed(() => queryResult.data.value?.data ?? [])
    const columns = computed(() => queryResult.data.value?.columns ?? [])
    const meta = computed(
        () =>
            queryResult.data.value?.meta ?? {
                currentPage: queryState.value.page,
                perPage: queryState.value.perPage,
                total: 0,
                lastPage: 1,
            },
    )

    return {
        query: queryState,
        rows,
        columns,
        meta,
        pagination: meta,

        isPending: queryResult.isPending,
        isFetching: queryResult.isFetching,
        isError: queryResult.isError,
        error: queryResult.error,
        isPlaceholderData: queryResult.isPlaceholderData,

        setPage,
        setPerPage,
        setSearch,
        setFilters,
        setSorts,

        refresh,
        prefetch,
    }
}
