# SQLite / factory schema maintenance (not School Phase 1 product)
## Purpose
Repair pre-existing SQLite migration and UserFactory issues that block full RefreshDatabase runs.

## Files
- staff create: remove indexes on non-existent section_id / department_id
- drop_orphan_staff_indexes_without_columns repair migration
- enrollments phase4: drop named indexes before SQLite table rebuild
- terms phase3: SQLite rebuild to drop school_id
- UserFactory: username + Profile email (users table has no email)

## Not School Management product scope
