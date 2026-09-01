# Phase 5 — Placement & Registration Numbers

## Domain boundaries

| Concept | Meaning |
|--------|---------|
| Profile | Person identity |
| Student | School-scoped capacity for a Profile |
| Enrollment | Registration of a student in a school/session |
| Placement (`student_session_placements`) | Academic location over time (class level + section) — **integer PK** |
| Admission Number | Permanent school admission identity |
| Registration Number | Mutable register identity within configured scope |

## Placement history

Mid-session section moves **end** the current placement for **that academic session only** (`left_at`, `is_current=false`) and create a **new** row. Placements in other sessions are not closed.

## Capacity convention (ClassSection)

- `capacity = 0` → **uncapped / not configured** (unlimited)
- `capacity > 0` → hard capacity; automatic allocation skips full sections
- Capacity override applies only when a positive configured capacity has been reached and the actor has permission

## Capacity concurrency

`allocateForEnrollment` and `placeManually` each establish `DB::transaction`. Within the transaction:

1. Lock candidate section row(s) (`lockForUpdate`)
2. Count active placements under the same lock
3. Create placement + registration assignment

Section-row lock is held for the entire placement mutation.

## Admission Number

Generated via `IdGenerator::generate('admission_number', $school)` using `id_sequences` (row lock + `insertOrIgnore` first-row seed). Uniqueness: `uq_students_school_admission_number`. Immutable after assignment (Student model guard). Unaffected by placement or registration changes.

## Registration Number

- History: `registration_number_histories` (immutable; allows historical reuse after `effective_to`)
- **Current uniqueness**: `registration_number_assignments` unique `(school_id, scope_key, registration_number)`
- Assignment is insert-into-assignments (DB rejects collisions); retry with next sequence on unique violation
- Settings key: `academic.registration_number` (`scope`, `regenerate_on_section_change`, etc.)

## Sequences

- `id_sequences` + `insertOrIgnore` + `SELECT … FOR UPDATE` is authoritative
- Phase 5 types (`admission_number`, `registration_number`, `student_id`) **require** the sequences table — no silent cache fallback
- Cache is best-effort mirror only; legacy non-Phase-5 types may still use cache if sequences table is absent

## Legacy pivot

`student_class_section_pivot` is mirrored for `ClassSection::currentStudents()` compatibility, **scoped by academic_session_id**. Placement table remains the source of truth.

## Registration assignment invariant (Phase 5)

A student has **at most one current registration number per school**.
Enforced by:

* `unique(school_id, student_id)` on `registration_number_assignments` (`uq_regnum_assignment_student`)
* `unique(school_id, scope_key, registration_number)` for number uniqueness within scope
* Student row `lockForUpdate` at the start of `RegistrationNumberService::assign()`

On reassignment, existing assignment rows for that student at that school are released first.

## Admission Number concurrency

`ensureAdmissionNumber()` locks the Student row (`SELECT … FOR UPDATE`), re-checks
`admission_number` under the lock, then assigns. Concurrent callers for the same
student cannot both observe NULL and overwrite each other.

## Collision retry (PostgreSQL-safe)

Each registration claim attempt runs inside a nested `DB::transaction`
(savepoint). Unique-constraint failures roll back only the savepoint, so:

* the outer transaction stays usable;
* no orphaned `registration_number_histories` row remains from the failed attempt.

## Concurrency testing note

True multi-writer races require PostgreSQL/MySQL and `pcntl_fork` (or equivalent).
SQLite serializes writers; concurrency tests in the suite **skip** (Pest `markTestSkipped`)
when the driver or process model cannot demonstrate the race. Sequential capacity
non-overfill and sequence uniqueness remain covered for all drivers.
