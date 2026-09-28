/**
 * DataTable selection state — resource-agnostic.
 * Manages explicit IDs and query selection independently of the live table query.
 */
import { computed, ref, type Ref } from 'vue'
import type {
    DataTableQuery,
    DataTableSelection,
    DataTableSelectionQuery,
    DataTableFilters,
} from './types'
import { DEFAULT_MAX_SELECTION_IDS } from './types'

export interface UseDataTableSelectionOptions {
    maxIds?: number
    /** Live table query — used only to detect "selection based on different query" */
    currentQuery?: Ref<DataTableQuery>
}

function membershipFromQuery(q: DataTableQuery): DataTableSelectionQuery {
    const out: DataTableSelectionQuery = {}
    if (q.search) out.search = q.search
    if (q.filters?.conditions?.length) {
        out.filters = { conditions: [...q.filters.conditions] }
    }
    return out
}

function membershipEqual(a: DataTableSelectionQuery, b: DataTableSelectionQuery): boolean {
    return JSON.stringify(a) === JSON.stringify(b)
}

export function useDataTableSelection(options: UseDataTableSelectionOptions = {}) {
    const maxIds = options.maxIds ?? DEFAULT_MAX_SELECTION_IDS

    const selection = ref<DataTableSelection | null>(null)
    /** Captured membership when converting page → query selection */
    const selectionMembershipSnapshot = ref<DataTableSelectionQuery | null>(null)

    const mode = computed<'none' | 'ids' | 'query'>(() => {
        if (!selection.value) return 'none'
        return selection.value.type
    })

    const selectedIds = computed<Array<string | number>>(() => {
        if (selection.value?.type === 'ids') return selection.value.ids
        return []
    })

    const selectedCount = computed(() => {
        if (!selection.value) return 0
        if (selection.value.type === 'ids') return selection.value.ids.length
        // Query selection count is unknown on the client until backend resolves
        return -1
    })

    const isQuerySelection = computed(() => selection.value?.type === 'query')

    /**
     * True when selection is query-based and its membership differs from the live table query.
     * UI should indicate the selection is based on a previous query.
     */
    const isSelectionBasedOnDifferentQuery = computed(() => {
        if (selection.value?.type !== 'query' || !selectionMembershipSnapshot.value) {
            return false
        }
        const live = options.currentQuery?.value
        if (!live) return false
        return !membershipEqual(selectionMembershipSnapshot.value, membershipFromQuery(live))
    })

    function clearSelection() {
        selection.value = null
        selectionMembershipSnapshot.value = null
    }

    function setIds(ids: Array<string | number>) {
        const unique: Array<string | number> = []
        const seen = new Set<string>()
        for (const id of ids) {
            const key = String(id)
            if (!seen.has(key)) {
                seen.add(key)
                unique.push(id)
            }
        }
        if (unique.length === 0) {
            clearSelection()
            return
        }
        if (unique.length > maxIds) {
            throw new Error(`Selection exceeds maximum of ${maxIds} explicit IDs.`)
        }
        selection.value = { type: 'ids', ids: unique }
        selectionMembershipSnapshot.value = null
    }

    function toggleId(id: string | number, selected: boolean) {
        const current = selection.value?.type === 'ids' ? [...selection.value.ids] : []
        const key = String(id)
        const idx = current.findIndex((x) => String(x) === key)
        if (selected && idx === -1) {
            current.push(id)
        } else if (!selected && idx !== -1) {
            current.splice(idx, 1)
        }
        setIds(current)
    }

    function selectPageIds(pageIds: Array<string | number>) {
        const current = selection.value?.type === 'ids' ? [...selection.value.ids] : []
        const seen = new Set(current.map(String))
        for (const id of pageIds) {
            if (!seen.has(String(id))) {
                current.push(id)
                seen.add(String(id))
            }
        }
        setIds(current)
    }

    function deselectPageIds(pageIds: Array<string | number>) {
        if (selection.value?.type !== 'ids') return
        const remove = new Set(pageIds.map(String))
        setIds(selection.value.ids.filter((id) => !remove.has(String(id))))
    }

    /**
     * Convert current ID selection into a query selection using the
     * canonical normalized membership from the live table query.
     * Captures the snapshot at conversion time — never reconstructed later.
     */
    function convertToQuerySelection(liveQuery: DataTableQuery) {
        const membership = membershipFromQuery(liveQuery)
        selection.value = { type: 'query', query: membership }
        selectionMembershipSnapshot.value = membership
    }

    function setQuerySelection(query: DataTableSelectionQuery) {
        selection.value = { type: 'query', query: { ...query } }
        selectionMembershipSnapshot.value = { ...query }
    }

    function getCanonicalSelection(): DataTableSelection | null {
        return selection.value
    }

    return {
        selection,
        mode,
        selectedIds,
        selectedCount,
        isQuerySelection,
        isSelectionBasedOnDifferentQuery,
        selectionMembershipSnapshot,
        maxIds,
        clearSelection,
        setIds,
        toggleId,
        selectPageIds,
        deselectPageIds,
        convertToQuerySelection,
        setQuerySelection,
        getCanonicalSelection,
    }
}
