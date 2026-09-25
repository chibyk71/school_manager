const MATCH_MODE_MAP = {
  equals: 'equals', contains: 'contains', startsWith: 'startsWith',
  lt: 'lessThan', between: 'between',
}
function mapPrimeVueMatchMode(matchMode) {
  if (!matchMode) return null
  return MATCH_MODE_MAP[matchMode] ?? null
}
function primeVueFiltersToCanonical(filters) {
  if (!filters) return undefined
  const conditions = []
  for (const [field, spec] of Object.entries(filters)) {
    if (field === 'global' || !spec) continue
    const value = spec.value
    if (value === null || value === undefined || value === '') continue
    const operator = mapPrimeVueMatchMode(spec.matchMode ?? 'contains')
    if (operator === null) continue
    conditions.push({ field, operator, value })
  }
  return conditions.length ? { conditions } : undefined
}
let fail = 0
function assert(c, m) { if (!c) { console.error('FAIL', m); fail++ } else console.log('OK', m) }
assert(mapPrimeVueMatchMode('bogus') === null, 'unknown mode null')
assert(mapPrimeVueMatchMode('contains') === 'contains', 'contains maps')
assert(primeVueFiltersToCanonical({ name: { value: 'x', matchMode: 'bogusMode' } }) === undefined, 'unknown mode skipped')
assert(primeVueFiltersToCanonical({ name: { value: 'x', matchMode: 'contains' } }).conditions[0].operator === 'contains', 'contains kept')
console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
