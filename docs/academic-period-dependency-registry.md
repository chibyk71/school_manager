# Academic Period Dependency Registry (Phase 4)

## Purpose

Answer whether an **Academic Session** or **Term** has dependent resources.

```
Session / Term lifecycle
        |
        | "Do I have dependencies?"
        v
 Academic dependency registry
        |
        +---- YES / NO
```

The registry does **not** decide whether a mutation is allowed. Session/Term lifecycle services own deletion and date-immutability rules; they ask the registry via the existing operational-data boundaries.

## Dependency vs business activity

A registry row means only: *this resource depends on this academic period*. Academic does **not** inspect Enrollment status, Admission outcome, application workflow, or placement currency.

## Table

`academic_period_usages`

| Column | Notes |
|--------|--------|
| school_id | Same school as session and resource |
| academic_session_id | Always set |
| term_id | Null = session-level; set = term-level (term must belong to session) |
| resource_type / resource_id | Polymorphic; uniqueness `(school_id, resource_type, resource_id)` |

No soft deletes on the registry — it represents **current** dependencies only.

## Opt-in

Resources implement `App\Contracts\Academic\TracksAcademicUsage` and use `App\Traits\TracksAcademicUsage`.

Initial tracked resources (all **session-level** in current domain):

- `StudentApplication`
- `Admission`
- `Enrollment`
- `StudentSessionPlacement` (school via Student)

Exam is **out of scope** for Phase 4.

## Lifecycle synchronization

Trait hooks: created → register; soft-deleted → unregister; restored → register; force-deleted → unregister; session/term FK changes → re-sync.

Owning services should keep resource mutations in a DB transaction so registry updates commit or roll back with the resource. The trait does not open its own transaction.

## Query semantics

- `hasSessionDependencies(session)` — any row with that `academic_session_id` (includes term-level rows under the session).
- `hasTermDependencies(term)` — rows with that `term_id` only.

## Boundaries

- `AcademicSessionOperationalDataBoundary` → `RegistryAcademicSessionOperationalData`
- `TermOperationalDataBoundary` → `RegistryTermOperationalData`

Null implementations remain available for tests that need an empty boundary.

## Backfill

```bash
php artisan academic:backfill-period-usages
php artisan academic:backfill-period-usages --dry-run
php artisan academic:backfill-period-usages --school=<uuid>
```

Idempotent. Fails loudly on cross-school or invalid session relationships. Write mode runs in one DB transaction and exits non-zero on contradiction.

## Concurrency protocol (lock order)

Global lock hierarchy — **must not be inverted**:

```
SCHOOL
  ↓
SESSION
  ↓
TERM
```

| Path | Order |
|------|--------|
| `AcademicSessionLifecycleService` | `AcademicPeriodLock::lockSchool` → lock session rows |
| `TermLifecycleService::lockSessionTerms` | resolve school (no row lock) → `lockSchool` → lock session → lock terms |
| `AcademicPeriodUsageRegistry` register/unregister | `lockSchool` then registry write |
| Tracked resource create (in txn) | trait `creating` → `lockSchool` when already in transaction |

Never lock SESSION before SCHOOL. Term mutations resolve the session's `school_id` **without** `lockForUpdate` first, acquire the school lock, then lock session and term rows.

## Future modules

1. Implement `TracksAcademicUsage` on the model (or compose the trait).
2. Ensure `academicUsageSchoolId` / `SessionId` / `TermId` reflect real FKs.
3. Prefer mutating through transactional services.
4. No Academic change required beyond the registry already listening via model events.
