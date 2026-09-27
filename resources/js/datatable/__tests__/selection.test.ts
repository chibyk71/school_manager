/**
 * Phase 6 selection composable unit tests.
 * Run with: npx vitest run resources/js/datatable/__tests__/selection.test.ts
 */
import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { useDataTableSelection } from '../useDataTableSelection'
import type { DataTableQuery } from '../types'

describe('useDataTableSelection', () => {
    it('starts with no selection', () => {
        const s = useDataTableSelection()
        expect(s.mode.value).toBe('none')
        expect(s.selectedCount.value).toBe(0)
    })

    it('sets and deduplicates ids', () => {
        const s = useDataTableSelection()
        s.setIds([1, 2, 2, 3])
        expect(s.mode.value).toBe('ids')
        expect(s.selectedIds.value).toEqual([1, 2, 3])
        expect(s.selectedCount.value).toBe(3)
    })

    it('preserves ids across conceptual page changes', () => {
        const s = useDataTableSelection()
        s.setIds([10, 20])
        s.selectPageIds([30, 40])
        expect(s.selectedIds.value).toEqual([10, 20, 30, 40])
        s.deselectPageIds([20, 30])
        expect(s.selectedIds.value).toEqual([10, 40])
    })

    it('clears selection', () => {
        const s = useDataTableSelection()
        s.setIds([1])
        s.clearSelection()
        expect(s.mode.value).toBe('none')
        expect(s.getCanonicalSelection()).toBeNull()
    })

    it('converts page selection to query selection with snapshot', () => {
        const live = ref<DataTableQuery>({
            page: 1,
            perPage: 50,
            search: 'active',
            filters: { conditions: [{ field: 'status', operator: 'equals', value: 'active' }] },
        })
        const s = useDataTableSelection({ currentQuery: live })
        s.setIds([1, 2, 3])
        s.convertToQuerySelection(live.value)
        expect(s.isQuerySelection.value).toBe(true)
        expect(s.selection.value).toEqual({
            type: 'query',
            query: {
                search: 'active',
                filters: { conditions: [{ field: 'status', operator: 'equals', value: 'active' }] },
            },
        })
        // Change live query — selection membership must remain the snapshot
        live.value = {
            page: 1,
            perPage: 50,
            search: 'inactive',
            filters: { conditions: [{ field: 'status', operator: 'equals', value: 'inactive' }] },
        }
        expect(s.isSelectionBasedOnDifferentQuery.value).toBe(true)
        expect(s.selectionMembershipSnapshot.value?.search).toBe('active')
    })

    it('rejects oversized id selection', () => {
        const s = useDataTableSelection({ maxIds: 3 })
        expect(() => s.setIds([1, 2, 3, 4])).toThrow(/maximum/)
    })

    it('does not include page or sorts in query selection', () => {
        const s = useDataTableSelection()
        s.convertToQuerySelection({
            page: 5,
            perPage: 25,
            search: 'x',
            sorts: [{ field: 'name', direction: 'asc' }],
        })
        const sel = s.getCanonicalSelection()
        expect(sel?.type).toBe('query')
        if (sel?.type === 'query') {
            expect(sel.query).not.toHaveProperty('page')
            expect(sel.query).not.toHaveProperty('perPage')
            expect(sel.query).not.toHaveProperty('sorts')
        }
    })
})
