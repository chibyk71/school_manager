# SQLite schema maintenance (shared infrastructure)

Branch: `fix/sqlite-schema-maintenance-clean`

## Problems addressed

1. **Staff indexes on non-existent columns**  
   `create_staff_table` historically indexed `section_id` / `department_id` without those columns.  
   SQLite later fails when rebuilding `devices` (`drop column ip`) with  
   `error in index staff_school_id_section_id_index after drop column: no such column: section_id`.

2. **Enrollment SQLite rebuild**  
   Named indexes must be dropped before `RENAME TABLE` so the rebuilt table can recreate the same names.

3. **Terms SQLite `school_id` drop**  
   Table rebuild without `school_id`, then **re-create** intended indexes/uniques (name unique, session/active, status, ordinal, date range) plus `terms_session_ordinal_unique`.

4. **UserFactory**  
   Align with username-centric users schema and Profile email.

## Ordering (critical for existing SQLite DBs)

| Migration | Role |
|-----------|------|
| `2025_01_13_124343_create_staff_table` | Corrected create (no bad indexes) for **fresh** installs |
| `2026_02_15_111554_drop_orphan_staff_indexes_without_columns` | Repair **before** `remove_ip_from_devices` so **existing** SQLite DBs with bad indexes can proceed |
| `2026_02_15_111555_remove_ip_from_devices_table` | devices.ip drop (previously failed on SQLite) |
| `2026_10_23_120000_drop_orphan_staff_indexes_without_columns` | Second idempotent pass for engines that never failed at devices |

## Scope

Does **not** include School Management product logic. School branch must not reintroduce these files as product scope.
