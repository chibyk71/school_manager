const MATCH_MODE_MAP = {
  equals: 'equals', contains: 'contains', startsWith: 'startsWith',
  lt: 'lessThan', between: 'between',
}
function primeVueSortToCanonical(sortField, sortOrder, multiSortMeta) {
  if (multiSortMeta?.length) {
    return multiSortMeta.filter(m => m.field && (m.order === 1 || m.order === -1))
      .map(m => ({ field: m.field, direction: m.order === -1 ? 'desc' : 'asc' }))
  }
  if (typeof sortField === 'string' && sortField && (sortOrder === 1 || sortOrder === -1)) {
    return [{ field: sortField, direction: sortOrder === -1 ? 'desc' : 'asc' }]
  }
  return []
}
function primeVueFiltersToCanonical(filters) {
  if (!filters) return undefined
  const conditions = []
  for (const [field, spec] of Object.entries(filters)) {
    if (field === 'global' || !spec) continue
    const value = spec.value
    if (value === null || value === undefined || value === '') continue
    conditions.push({ field, operator: MATCH_MODE_MAP[spec.matchMode ?? 'contains'] ?? 'contains', value })
  }
  return conditions.length ? { conditions } : undefined
}
let fail = 0
function assert(c, m) { if (!c) { console.error('FAIL', m); fail++ } else console.log('OK', m) }
assert(JSON.stringify(primeVueSortToCanonical('name', -1)) === JSON.stringify([{field:'name',direction:'desc'}]), 'sort -1 desc')
assert(primeVueSortToCanonical('name', 0).length === 0, 'sort 0 empty')
assert(primeVueFiltersToCanonical({ global: { value: 'x' }, name: { value: 'Ada', matchMode: 'contains' } }).conditions[0].field === 'name', 'filter skips global')
assert(primeVueFiltersToCanonical({ name: { value: '', matchMode: 'contains' } }) === undefined, 'empty filter')
console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
