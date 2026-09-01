# Phase 5 — Placement & Registration Numbers

## Boundaries
- Profile = person; Student = school capacity; Enrollment = session registration
- Placement = academic location over time (history preserved)
- Admission Number = permanent school-scoped identity
- Registration Number = mutable scoped register identity

## Capacity
Active occupancy = placements with is_current=true and left_at null.
Sections locked with lockForUpdate; ordered by sort_order then name.
Override requires placements.capacity_override (or enrollments.finalize / placements.manage).

## Sequences
id_sequences + lockForUpdate is authoritative; cache is non-authoritative.
