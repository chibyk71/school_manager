/**
 * Phase 6 selection composable contract tests (no vitest dependency).
 * Run: node resources/js/datatable/__tests__/selection.test.mjs
 *
 * Mirrors useDataTableSelection semantics without Vue runtime.
 */

function membershipFromQuery(q) {
  const out = {}
  if (q.search) out.search = q.search
  if (q.filters?.conditions?.length) {
    out.filters = { conditions: [...q.filters.conditions] }
  }
  return out
}

function membershipEqual(a, b) {
  return JSON.stringify(a) === JSON.stringify(b)
}

function createSelection(maxIds = 5000) {
  let selection = null
  let snapshot = null

  return {
    get mode() {
      if (!selection) return 'none'
      return selection.type
    },
    get selectedIds() {
      return selection?.type === 'ids' ? selection.ids : []
    },
    get selectedCount() {
      if (!selection) return 0
      if (selection.type === 'ids') return selection.ids.length
      return -1
    },
    get isQuerySelection() {
      return selection?.type === 'query'
    },
    get snapshot() {
      return snapshot
    },
    isSelectionBasedOnDifferentQuery(liveQuery) {
      if (selection?.type !== 'query' || !snapshot) return false
      return !membershipEqual(snapshot, membershipFromQuery(liveQuery))
    },
    clearSelection() {
      selection = null
      snapshot = null
    },
    setIds(ids) {
      const unique = []
      const seen = new Set()
      for (const id of ids) {
        const key = String(id)
        if (!seen.has(key)) {
          seen.add(key)
          unique.push(id)
        }
      }
      if (unique.length === 0) {
        this.clearSelection()
        return
      }
      if (unique.length > maxIds) {
        throw new Error(`Selection exceeds maximum of ${maxIds} explicit IDs.`)
      }
      selection = { type: 'ids', ids: unique }
      snapshot = null
    },
    selectPageIds(pageIds) {
      const current = selection?.type === 'ids' ? [...selection.ids] : []
      const seen = new Set(current.map(String))
      for (const id of pageIds) {
        if (!seen.has(String(id))) {
          current.push(id)
          seen.add(String(id))
        }
      }
      this.setIds(current)
    },
    deselectPageIds(pageIds) {
      if (selection?.type !== 'ids') return
      const remove = new Set(pageIds.map(String))
      this.setIds(selection.ids.filter((id) => !remove.has(String(id))))
    },
    convertToQuerySelection(liveQuery) {
      const membership = membershipFromQuery(liveQuery)
      selection = { type: 'query', query: membership }
      snapshot = membership
    },
    getCanonicalSelection() {
      return selection
    },
  }
}

let fail = 0
function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL', msg)
    fail++
  } else {
    console.log('OK', msg)
  }
}

const s = createSelection()
assert(s.mode === 'none', 'starts with no selection')
assert(s.selectedCount === 0, 'count 0')

s.setIds([1, 2, 2, 3])
assert(s.mode === 'ids', 'ids mode')
assert(JSON.stringify(s.selectedIds) === JSON.stringify([1, 2, 3]), 'dedupe ids')
assert(s.selectedCount === 3, 'count 3')

s.selectPageIds([30, 40])
assert(JSON.stringify(s.selectedIds) === JSON.stringify([1, 2, 3, 30, 40]), 'cross-page select')
s.deselectPageIds([2, 30])
assert(JSON.stringify(s.selectedIds) === JSON.stringify([1, 3, 40]), 'deselect page ids')

s.clearSelection()
assert(s.mode === 'none', 'clear')
assert(s.getCanonicalSelection() === null, 'canonical null')

const live = {
  page: 1,
  perPage: 50,
  search: 'active',
  filters: { conditions: [{ field: 'status', operator: 'equals', value: 'active' }] },
}
s.setIds([1, 2, 3])
s.convertToQuerySelection(live)
assert(s.isQuerySelection === true, 'query mode')
assert(s.snapshot.search === 'active', 'snapshot search')
assert(
  s.isSelectionBasedOnDifferentQuery({
    page: 1,
    perPage: 50,
    search: 'inactive',
    filters: { conditions: [{ field: 'status', operator: 'equals', value: 'inactive' }] },
  }) === true,
  'different query detected',
)
assert(s.snapshot.search === 'active', 'snapshot immutable after live change')

const limited = createSelection(3)
let threw = false
try {
  limited.setIds([1, 2, 3, 4])
} catch {
  threw = true
}
assert(threw, 'rejects oversized id selection')

s.convertToQuerySelection({
  page: 5,
  perPage: 25,
  search: 'x',
  sorts: [{ field: 'name', direction: 'asc' }],
})
const sel = s.getCanonicalSelection()
assert(sel.type === 'query', 'query type')
assert(!('page' in sel.query), 'no page in membership')
assert(!('perPage' in sel.query), 'no perPage in membership')
assert(!('sorts' in sel.query), 'no sorts in membership')

console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
