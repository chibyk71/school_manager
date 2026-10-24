<?php

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared SQLite schema maintenance (not School product).
 *
 * PHPUnit (phpunit.xml) forces:
 *   DB_CONNECTION=sqlite
 *   DB_DATABASE=:memory:
 *
 * That is independent of `php artisan migrate --env=testing`, which loads
 * .env / .env.testing and may point at MySQL. Prefer:
 *   DB_CONNECTION=sqlite DB_DATABASE=":memory:" php artisan migrate --force
 * or a file DB for inspection.
 */
class SqliteSchemaMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phpunit_uses_sqlite_memory_not_mysql(): void
    {
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());
        $this->assertSame(':memory:', Schema::getConnection()->getDatabaseName());
    }

    public function test_staff_table_has_no_indexes_on_missing_columns(): void
    {
        if (! Schema::hasTable('staff')) {
            $this->markTestSkipped('staff table required');
        }

        $indexes = $this->sqliteIndexNames('staff');

        $this->assertNotContains('staff_school_id_section_id_index', $indexes);
        $this->assertNotContains('staff_department_id_index', $indexes);
        $this->assertContains('staff_school_id_index', $indexes);
    }

    public function test_devices_ip_column_is_absent_after_migrations(): void
    {
        if (! Schema::hasTable('devices')) {
            $this->markTestSkipped('devices table required');
        }

        $this->assertFalse(
            Schema::hasColumn('devices', 'ip'),
            'devices.ip should be dropped; if migrate failed earlier, this never runs'
        );
    }

    /**
     * Existing-DB path: staff create already recorded with legacy-named indexes present.
     * Repair must drop them so a subsequent devices column drop (table rebuild) succeeds.
     */
    public function test_legacy_named_staff_indexes_are_repaired_before_devices_rebuild(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only');
        }
        if (! Schema::hasTable('staff') || ! Schema::hasTable('devices')) {
            $this->markTestSkipped('staff and devices required');
        }

        DB::statement('DROP INDEX IF EXISTS staff_school_id_section_id_index');
        DB::statement('DROP INDEX IF EXISTS staff_department_id_index');
        DB::statement('CREATE INDEX staff_school_id_section_id_index ON staff (school_id)');
        DB::statement('CREATE INDEX staff_department_id_index ON staff (school_id)');

        $this->assertContains('staff_school_id_section_id_index', $this->sqliteIndexNames('staff'));

        $migration = require database_path('migrations/2026_02_15_111554_drop_orphan_staff_indexes_without_columns.php');
        $migration->up();

        $indexes = $this->sqliteIndexNames('staff');
        $this->assertNotContains('staff_school_id_section_id_index', $indexes);
        $this->assertNotContains('staff_department_id_index', $indexes);

        if (! Schema::hasColumn('devices', 'ip')) {
            Schema::table('devices', function ($table) {
                $table->string('ip')->nullable();
            });
        }
        Schema::table('devices', function ($table) {
            $table->dropColumn('ip');
        });

        $this->assertFalse(Schema::hasColumn('devices', 'ip'));
    }

    public function test_terms_session_ordinal_and_name_uniques_exist(): void
    {
        if (! Schema::hasTable('terms')) {
            $this->markTestSkipped('terms table required');
        }

        $indexes = $this->sqliteIndexNames('terms');

        $this->assertTrue(
            $this->indexNameContains($indexes, 'terms_session_ordinal_unique')
            || $this->indexNameContains($indexes, 'ordinal'),
            'terms_session_ordinal_unique (or equivalent unique) must exist after phase3'
        );

        $this->assertTrue(
            in_array('terms_session_name_unique', $indexes, true)
            || $this->indexNameContains($indexes, 'session_name'),
            'terms_session_name_unique must be restored after SQLite rebuild'
        );

        foreach (['terms_ordinal_idx', 'terms_status_idx', 'terms_date_range_idx'] as $optional) {
            if ($optional === 'terms_status_idx' && ! Schema::hasColumn('terms', 'status')) {
                continue;
            }
            if ($optional === 'terms_date_range_idx' && (! Schema::hasColumn('terms', 'start_date') || ! Schema::hasColumn('terms', 'end_date'))) {
                continue;
            }
            $this->assertContains($optional, $indexes, "Expected index {$optional} after terms rebuild");
        }
    }

    public function test_enrollments_named_indexes_exist_after_phase4(): void
    {
        if (! Schema::hasTable('enrollments')) {
            $this->markTestSkipped('enrollments table required');
        }

        $indexes = $this->sqliteIndexNames('enrollments');

        foreach ([
            'uq_enrollments_admission_id',
            'idx_enrollments_school_status',
            'idx_enrollments_school_session',
            'idx_enrollments_student_session',
            'idx_enrollments_school_deleted',
        ] as $expected) {
            $this->assertContains(
                $expected,
                $indexes,
                "Enrollment index {$expected} missing after phase4 SQLite rebuild"
            );
        }
    }

    public function test_enrollment_sqlite_rebuild_preserves_rows_and_indexes(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only');
        }
        if (! Schema::hasTable('enrollments') || ! Schema::hasTable('schools') || ! Schema::hasTable('academic_sessions')) {
            $this->markTestSkipped('enrollments + parents required');
        }

        $schoolId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $enrollmentId = (string) Str::uuid();

        if (! DB::table('schools')->where('id', $schoolId)->exists()) {
            $schoolRow = [
                'id' => $schoolId,
                'name' => 'Maint School',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('schools', 'code')) {
                $schoolRow['code'] = 'MNT1';
            }
            if (Schema::hasColumn('schools', 'slug')) {
                $schoolRow['slug'] = 'maint-school';
            }
            if (Schema::hasColumn('schools', 'is_active')) {
                $schoolRow['is_active'] = false;
            }
            DB::table('schools')->insert($schoolRow);
        }

        if (! DB::table('academic_sessions')->where('id', $sessionId)->exists()) {
            $sessionRow = [
                'id' => $sessionId,
                'school_id' => $schoolId,
                'name' => '2026 Session',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('academic_sessions', 'start_date')) {
                $sessionRow['start_date'] = '2026-01-01';
            }
            if (Schema::hasColumn('academic_sessions', 'end_date')) {
                $sessionRow['end_date'] = '2026-12-31';
            }
            DB::table('academic_sessions')->insert($sessionRow);
        }

        $enrollRow = [
            'id' => $enrollmentId,
            'school_id' => $schoolId,
            'academic_session_id' => $sessionId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('enrollments', 'student_id')) {
            $enrollRow['student_id'] = null;
        }
        DB::table('enrollments')->insert($enrollRow);

        $this->assertNotNull(DB::table('enrollments')->where('id', $enrollmentId)->first());

        $migration = require database_path('migrations/2026_08_31_100000_phase4_enrollment_nullable_student_and_requirements.php');
        $ref = new \ReflectionClass($migration);
        if (! $ref->hasMethod('rebuildEnrollmentsForSqlite')) {
            $this->markTestSkipped('rebuildEnrollmentsForSqlite not present');
        }
        $method = $ref->getMethod('rebuildEnrollmentsForSqlite');
        $method->setAccessible(true);
        $method->invoke($migration);

        $after = DB::table('enrollments')->where('id', $enrollmentId)->first();
        $this->assertNotNull($after, 'Enrollment row must survive SQLite rebuild');
        $this->assertSame('draft', $after->status);
        $this->assertSame($schoolId, $after->school_id);

        foreach ([
            'uq_enrollments_admission_id',
            'idx_enrollments_school_status',
            'idx_enrollments_school_session',
            'idx_enrollments_student_session',
            'idx_enrollments_school_deleted',
        ] as $expected) {
            $this->assertContains($expected, $this->sqliteIndexNames('enrollments'));
        }
    }

    public function test_terms_sqlite_rebuild_preserves_rows_and_uniques(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only');
        }
        if (! Schema::hasTable('terms') || ! Schema::hasTable('academic_sessions') || ! Schema::hasTable('schools')) {
            $this->markTestSkipped('terms + parents required');
        }

        $schoolId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $termId = (string) Str::uuid();

        if (! DB::table('schools')->where('id', $schoolId)->exists()) {
            $schoolRow = [
                'id' => $schoolId,
                'name' => 'Terms School',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('schools', 'slug')) {
                $schoolRow['slug'] = 'terms-school';
            }
            if (Schema::hasColumn('schools', 'is_active')) {
                $schoolRow['is_active'] = false;
            }
            DB::table('schools')->insert($schoolRow);
        }

        if (! DB::table('academic_sessions')->where('id', $sessionId)->exists()) {
            $sessionRow = [
                'id' => $sessionId,
                'school_id' => $schoolId,
                'name' => 'Terms Session',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('academic_sessions', 'start_date')) {
                $sessionRow['start_date'] = '2026-01-01';
            }
            if (Schema::hasColumn('academic_sessions', 'end_date')) {
                $sessionRow['end_date'] = '2026-12-31';
            }
            DB::table('academic_sessions')->insert($sessionRow);
        }

        $termRow = [
            'id' => $termId,
            'academic_session_id' => $sessionId,
            'name' => 'First Term',
            'ordinal_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('terms', 'school_id')) {
            $termRow['school_id'] = $schoolId;
        }
        if (Schema::hasColumn('terms', 'status')) {
            $termRow['status'] = 'pending';
        }
        DB::table('terms')->insert($termRow);

        $migration = require database_path('migrations/2026_09_12_140000_phase3_term_ownership_and_sequence.php');
        $ref = new \ReflectionClass($migration);

        if (! $ref->hasMethod('rebuildTermsWithoutSchoolIdForSqlite')) {
            $this->markTestSkipped('rebuildTermsWithoutSchoolIdForSqlite not present');
        }

        if (! Schema::hasColumn('terms', 'school_id')) {
            Schema::table('terms', function ($table) {
                $table->uuid('school_id')->nullable();
            });
            DB::table('terms')->where('id', $termId)->update(['school_id' => $schoolId]);
        }
        $method = $ref->getMethod('rebuildTermsWithoutSchoolIdForSqlite');
        $method->setAccessible(true);
        $method->invoke($migration);
        if ($ref->hasMethod('ensureSessionOrdinalUnique')) {
            $ensure = $ref->getMethod('ensureSessionOrdinalUnique');
            $ensure->setAccessible(true);
            $ensure->invoke($migration);
        }

        $after = DB::table('terms')->where('id', $termId)->first();
        $this->assertNotNull($after, 'Term row must survive SQLite rebuild');
        $this->assertSame('First Term', $after->name);
        $this->assertSame(1, (int) $after->ordinal_number);
        $this->assertFalse(Schema::hasColumn('terms', 'school_id'));

        $indexes = $this->sqliteIndexNames('terms');
        $this->assertContains('terms_session_name_unique', $indexes);
        $this->assertTrue(
            in_array('terms_session_ordinal_unique', $indexes, true)
            || $this->indexNameContains($indexes, 'ordinal')
        );
    }

    public function test_user_factory_creates_user_without_email_column_requirement(): void
    {
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('users table required');
        }

        $user = \App\Models\User::factory()->create();
        $this->assertNotNull($user->id);
        if (Schema::hasColumn('users', 'username')) {
            $this->assertNotEmpty($user->username);
        }
    }

    /**
     * @return list<string>
     */
    private function sqliteIndexNames(string $table): array
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific index introspection');
        }

        $rows = DB::select("PRAGMA index_list('{$table}')");
        $names = [];
        foreach ($rows as $row) {
            $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
            if (is_string($name) && ! str_starts_with($name, 'sqlite_autoindex_')) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $indexes
     */
    private function indexNameContains(array $indexes, string $needle): bool
    {
        foreach ($indexes as $name) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}
