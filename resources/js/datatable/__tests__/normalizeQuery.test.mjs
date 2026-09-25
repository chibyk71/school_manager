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
let fail = 0
function assert(c, m) { if (!c) { console.error('FAIL', m); fail++ } else console.log('OK', m) }
assert(normalizeQuery({}).perPage === 50, 'default perPage 50')
assert(normalizeQuery({ page: 0 }).page === 1, 'page min 1')
assert(normalizeQuery({ perPage: 500 }).perPage === 100, 'perPage max 100')
console.log(fail ? `FAILED ${fail}` : 'ALL OK')
process.exit(fail ? 1 : 0)
