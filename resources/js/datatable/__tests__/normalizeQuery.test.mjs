function normalizeQuery(input) {
  const page = Math.max(1, Number(input?.page) || 1)
  let perPage = Number(input?.perPage)
  if (!Number.isFinite(perPage) || perPage < 1) perPage = 50
  perPage = Math.min(Math.floor(perPage), 100)
  const searchRaw = input?.search
  const search =
    searchRaw === null || searchRaw === undefined || String(searchRaw).trim() === ''
      ? undefined
      : String(searchRaw).trim()
  return { page, perPage, ...(search !== undefined ? { search } : {}) }
}

function getKey(resource, query) {
  const q = normalizeQuery(query)
  return ['datatable', resource, { search: q.search ?? null, filters: null, sorts: null, perPage: q.perPage }, q.page]
}

let fail = 0
function assert(c, m) {
  if (!c) { console.error('FAIL', m); fail++ }
  else console.log('OK', m)
}

assert(normalizeQuery({}).perPage === 50, 'default perPage 50')
assert(normalizeQuery({ page: 0 }).page === 1, 'page min 1')
assert(normalizeQuery({ perPage: 500 }).perPage === 100, 'perPage max 100')
assert(normalizeQuery({ search: '  a  ' }).search === 'a', 'trim search')
assert(normalizeQuery({ search: '   ' }).search === undefined, 'empty search')
const k1 = JSON.stringify(getKey('/dept', { page: 1, perPage: 50, search: 'x' }))
const k2 = JSON.stringify(getKey('/dept', { page: 1, perPage: 50, search: 'x' }))
assert(k1 === k2, 'stable keys')
const k3 = JSON.stringify(getKey('/dept', { page: 2, perPage: 50, search: 'x' }))
assert(k1 !== k3, 'page in key')
console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
