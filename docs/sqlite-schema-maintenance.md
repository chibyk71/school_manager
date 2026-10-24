# SQLite schema maintenance (shared infrastructure)

Branch: `fix/sqlite-schema-maintenance-clean`

## Database connection: tests vs artisan

| Command | Config source | Typical DB |
|---------|---------------|------------|
| `php artisan test` / PHPUnit | `phpunit.xml` `<env>` | **sqlite** `:memory:` |
| `php artisan migrate --env=testing` | `.env` / `.env.testing` | Often **MySQL** at `127.0.0.1:3306` |

PHPUnit sets:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

So a green SQLite unit suite does **not** prove `migrate --env=testing` against MySQL.

### Explicit SQLite migrate (file DB, inspectable)

```bash
# Unix
DB_CONNECTION=sqlite DB_DATABASE="/tmp/school_manager_maint.sqlite" php artisan migrate:fresh --force

# PowerShell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE="$env:TEMP\school_manager_maint.sqlite"
if (Test-Path $env:DB_DATABASE) { Remove-Item $env:DB_DATABASE }
php artisan migrate:fresh --force
```

Do not treat a MySQL connection refused as a migration logic failure.

## Problems addressed

1. **Staff indexes on non-existent columns** — fixed create + early repair before `devices` drop `ip`.
2. **Enrollment SQLite rebuild** — drop named indexes before rename; recreate on new table.
3. **Terms SQLite `school_id` drop** — rebuild + restore indexes/uniques.
4. **UserFactory** — username / Profile email.

## Ordering (existing SQLite DBs)

| Migration | Role |
|-----------|------|
| Corrected `create_staff_table` | Fresh installs |
| `2026_02_15_111554_drop_orphan_staff_indexes_without_columns` | Repair **before** `remove_ip_from_devices` |
| `2026_02_15_111555_remove_ip_from_devices_table` | devices.ip drop |
| `2026_10_23_120000_...` | Second idempotent pass |

## Scope

Not School Management product logic.
