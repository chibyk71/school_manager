# Phase 6 — Lifecycle Changes

## Scope

After initial enrollment, placement, and numbering (Phases 1–5), Phase 6 covers:

- Section change (same class level, same session)
- Class change (different class level, same session — administrative, not formal promotion)
- Integration with the **existing Promotion module** (promote / repeat / graduate)
- Transfer out
- Withdrawal
- Completion / graduation (via Promotion + `StudentStatusService`)

## Ownership boundaries

| Concern | Owner |
|--------|--------|
| Formal promote / repeat / graduate workflow | Promotion module (`PromotionBatch`, `ProcessStudentPromotion`, `PromotionHistory`) |
| Session-scoped placement history | `student_session_placements` + `PlacementAllocationService` |
| Registration numbers | `RegistrationNumberService` |
| Student lifecycle status | `StudentStatusService` |
| Enrollment registration state | `Enrollment` (`active` / `withdrawn` / `transferred_out` / `completed`) |

Do **not** create a second promotion engine or a duplicate promotion history table.

## Section / class change

Implemented on `PlacementAllocationService`:

- `changeSection(Student, School, sectionId, actor, options)`
- `changeClass(Student, School, levelId, sectionId, actor, options)`

Invariants:

1. School boundaries enforced on student, session, level, section.
2. Current placement for **that session only** is ended (`left_at`, `is_current=false`).
3. New placement row is created (`is_current=true`).
4. Capacity checked under section lock; override requires `placements.capacity_override`.
5. Admission number never changes.
6. Registration number follows `academic.registration_number` settings:
   - `regenerate_on_section_change`
   - `regenerate_on_class_change` (defaults to section policy when unset)

Routes:

- `POST placements/change-section/{student}`
- `POST placements/change-class/{student}`

Permissions: `placements.change_section`, `placements.change_class`.

## Promotion integration

`ProcessStudentPromotion` still owns batch execution and `PromotionHistory`.

Promote / repeat now prefer `PlacementAllocationService::placeForPromotionOutcome()` so next-session placement respects:

- session-scoped capacity
- registration-number policy (`regenerate_on_promotion`)
- Phase 5 assignment uniqueness

Graduate continues to call `StudentStatusService::markGraduated()`, which also closes active enrollments as `completed`.

## Transfer / withdrawal

`StudentStatusService`:

- `withdraw` → student `withdrawn`; active enrollments → `withdrawn`; current placement ended
- `transferOut` → student `transferred`; active enrollments → `transferred_out`; placement ended

No hard deletes of Student, Enrollment, Placement, Admission Number, or registration history.

## Enrollment vs Student status

- **Enrollment**: registration for a school/session (`draft` … `active` … terminal states)
- **Student status**: broader capacity at the school (`active`, `withdrawn`, `transferred`, `graduated`, …)

Changing status does not rewrite historical enrollments other than active ones for the school.

## Tests

`tests/Unit/StudentLifecycle/Phase6LifecycleChangesDomainTest.php`

```bash
php artisan test --filter=Phase6LifecycleChangesDomainTest
php artisan test --filter=Phase5
# Promotion regression when full suite is available
php artisan test --filter=Promotion
```
