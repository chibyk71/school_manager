# School Management Phase 1 — Review corrections (post-2dd185fd)

## Scope
Corrective work for blocking findings. No Phase 2 settings.

## 1. Requests
- `prepareForValidation()` no longer injects `is_active`.
- `state` and `is_active` remain `prohibited`.
- Type validation uses `App\Support\School\SchoolTypeCatalogue` (`private`, `government`, `community`).

## 2. Controller lifecycle
- `destroy` → `SchoolLifecycleService::softDelete` (capacity lock).
- `restore` → `SchoolLifecycleService::restore` (capacity recheck).
- `bulkToggleStatus` retired (HTTP 410) — cannot mutate `is_active` independently.

## 3. Migration
- After choosing lifecycle state, `is_active` is derived from state only:
  Active/Suspended → true; Draft/Ready/Archived → false.
- Never maps `is_active=true` → Active.
- Diagnostics remain in `data.migration_notes`.

## 4. Type catalogue
- Single source: `SchoolTypeCatalogue` used by Store, Update, readiness.

## 5. Frontend
- CreateEdit: draft-friendly requiredness; `contact_name`, description, motto;
  no `is_active` checkbox; lifecycle panel + readiness checklist on edit.
- Type options aligned to catalogue.

## 6. Tests
- Domain happy-path requires real primary address (no silent catch).
- Feature tests: prohibited fields, retired bulk toggle.
- Capacity limit override test; concurrency marked skipped on SQLite.

## 7. SchoolCreated / File Manager
- Create still dispatches `SchoolCreated` after **Draft** creation only.
- Lifecycle create sets `is_active=false` and state Draft — does not activate.
- School model soft-delete/restore cascade for storage providers unchanged.

## Owner verification still required
- Migrate copy of production data; report engine + before/after `state`/`is_active`.
- Run: `php artisan test --filter=SchoolLifecycle`
- Frontend build: `npm run build` / `vue-tsc`
- Concurrent capacity on MySQL/MariaDB/PostgreSQL.
