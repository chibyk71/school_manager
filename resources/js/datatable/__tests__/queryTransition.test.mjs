/**
 * Models the single-path query transition (no Vue runtime).
 * Verifies search + filters update atomically and clearing one preserves the other.
 */
function normalizeQuery(input) {
  const page = Math.max(1, Number(input?.page) || 1)
  let perPage = Number(input?.perPage)
  if (!Number.isFinite(perPage) || perPage < 1) perPage = 50
  perPage = Math.min(Math.floor(perPage), 100)
  const search = input?.search?.trim() || undefined
  const filters = input?.filters?.conditions?.length ? input.filters : undefined
  const sorts = input?.sorts?.length ? input.sorts : undefined
  return {
    page,
    perPage,
    ...(search ? { search } : {}),
    ...(filters ? { filters } : {}),
    ...(sorts ? { sorts } : {}),
  }
}

function setSearchAndFilters(state, search, filters) {
  return normalizeQuery({ ...state, search: search?.trim() || undefined, filters, page: 1 })
}

let fail = 0
function assert(c, m) { if (!c) { console.error('FAIL', m); fail++ } else console.log('OK', m) }

let state = normalizeQuery({ page: 3, perPage: 50 })
state = setSearchAndFilters(state, 'John', undefined)
assert(state.page === 1, 'search resets page')
assert(state.search === 'John', 'search set')
assert(!state.filters, 'no filters yet')

state = setSearchAndFilters(state, 'John', { conditions: [{ field: 'status', operator: 'equals', value: 'active' }] })
assert(state.search === 'John', 'search preserved with filter')
assert(state.filters.conditions.length === 1, 'filter set')
assert(state.page === 1, 'still page 1')

state = setSearchAndFilters(state, undefined, state.filters)
assert(!state.search, 'search cleared')
assert(state.filters.conditions[0].field === 'status', 'filter preserved when search cleared')

state = setSearchAndFilters(state, 'Ada', undefined)
assert(state.search === 'Ada', 'search set')
assert(!state.filters, 'filters cleared when passed undefined')

const a = setSearchAndFilters({ page: 2, perPage: 50 }, 'x', { conditions: [{ field: 'n', operator: 'contains', value: 'y' }] })
const b = setSearchAndFilters({ page: 2, perPage: 50 }, 'x', { conditions: [{ field: 'n', operator: 'contains', value: 'y' }] })
assert(JSON.stringify(a) === JSON.stringify(b), 'deterministic single transition')

console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
