<?php

namespace Tests\Unit\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared SQLite schema maintenance (not School product).
 *
 * PHPUnit (phpunit.xml) forces DB_CONNECTION=sqlite. Shell may still override
 * DB_DATABASE to a file path when verifying migrate:fresh explicitly.
 *
 * `php artisan migrate --env=testing` reads .env and may use MySQL — that is
 * unrelated to these SQLite tests.
 */
class SqliteSchemaMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phpunit_uses_sqlite_not_mysql(): void
    {
        // phpunit.xml sets sqlite; shell may override DB_DATABASE to a file path
        // (e.g. explicit migrate:fresh). Still must not be MySQL/Postgres.
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());
        $db = (string) Schema::getConnection()->getDatabaseName();
        $this->assertTrue(
            $db === ':memory:' || str_ends_with($db, '.sqlite') || str_contains($db, 'sqlite'),
            "Expected sqlite memory or file DB, got: {$db}"
        );
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
     * Repair-order simulation for existing databases.
     *
     * What this establishes:
     * - When the legacy *index names* are present on staff, the early repair
     *   migration removes them.
     * - After repair, a devices column drop (SQLite table rebuild) completes.
     *
     * What this does not establish:
     * - It does not reconstruct a historical sqlite_master entry that indexed
     *   a missing section_id column (SQLite will not CREATE INDEX on a missing
     *   column). It uses the same index *names* on a real column so the repair
     *   + rebuild path can be exercised.
     * - Fresh installs rely on the corrected create_staff_table (no bad indexes).
     * - Production installs that already recorded staff create depend on the
     *   early migration running before remove_ip_from_devices (ordering), which
     *   this test complements rather than fully replaying.
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

    /**
     * Prove UNIQUE constraints reject duplicates — not merely that index names exist.
     */
    public function test_terms_unique_constraints_reject_duplicate_ordinal_and_name(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only');
        }
        if (! Schema::hasTable('terms') || ! Schema::hasTable('academic_sessions') || ! Schema::hasTable('schools')) {
            $this->markTestSkipped('terms + parents required');
        }

        [$schoolId, $sessionId] = $this->insertSchoolAndSession('uniq');

        $base = [
            'academic_session_id' => $sessionId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('terms', 'status')) {
            $base['status'] = 'pending';
        }

        DB::table('terms')->insert(array_merge($base, [
            'id' => (string) Str::uuid(),
            'name' => 'First Term',
            'ordinal_number' => 1,
        ]));

        try {
            DB::table('terms')->insert(array_merge($base, [
                'id' => (string) Str::uuid(),
                'name' => 'Other Name',
                'ordinal_number' => 1,
            ]));
            $this->fail('Expected uniqueness violation for duplicate (academic_session_id, ordinal_number)');
        } catch (QueryException $e) {
            $this->assertTrue(
                $this->isUniqueConstraintFailure($e),
                'Expected UNIQUE constraint failure for ordinal, got: '.$e->getMessage()
            );
        }

        try {
            DB::table('terms')->insert(array_merge($base, [
                'id' => (string) Str::uuid(),
                'name' => 'First Term',
                'ordinal_number' => 2,
            ]));
            $this->fail('Expected uniqueness violation for duplicate (academic_session_id, name)');
        } catch (QueryException $e) {
            $this->assertTrue(
                $this->isUniqueConstraintFailure($e),
                'Expected UNIQUE constraint failure for name, got: '.$e->getMessage()
            );
        }

        DB::table('terms')->insert(array_merge($base, [
            'id' => (string) Str::uuid(),
            'name' => 'Second Term',
            'ordinal_number' => 2,
        ]));
        $this->assertSame(2, DB::table('terms')->where('academic_session_id', $sessionId)->count());
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

        [$schoolId, $sessionId] = $this->insertSchoolAndSession('enroll');
        $enrollmentId = (string) Str::uuid();

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

        [$schoolId, $sessionId] = $this->insertSchoolAndSession('terms');
        $termId = (string) Str::uuid();

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

        $base = [
            'academic_session_id' => $sessionId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('terms', 'status')) {
            $base['status'] = 'pending';
        }

        try {
            DB::table('terms')->insert(array_merge($base, [
                'id' => (string) Str::uuid(),
                'name' => 'Other',
                'ordinal_number' => 1,
            ]));
            $this->fail('Post-rebuild: expected UNIQUE failure for duplicate ordinal');
        } catch (QueryException $e) {
            $this->assertTrue($this->isUniqueConstraintFailure($e), $e->getMessage());
        }

        try {
            DB::table('terms')->insert(array_merge($base, [
                'id' => (string) Str::uuid(),
                'name' => 'First Term',
                'ordinal_number' => 9,
            ]));
            $this->fail('Post-rebuild: expected UNIQUE failure for duplicate name');
        } catch (QueryException $e) {
            $this->assertTrue($this->isUniqueConstraintFailure($e), $e->getMessage());
        }
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
     * @return array{0: string, 1: string} [schoolId, sessionId]
     */
    private function insertSchoolAndSession(string $prefix): array
    {
        $schoolId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $suffix = substr($schoolId, 0, 8);

        $schoolRow = [
            'id' => $schoolId,
            'name' => $prefix.' School',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('schools', 'code')) {
            $schoolRow['code'] = strtoupper(substr($prefix, 0, 4)).'-'.$suffix;
        }
        if (Schema::hasColumn('schools', 'email')) {
            $schoolRow['email'] = $prefix.'-'.$suffix.'@example.test';
        }
        if (Schema::hasColumn('schools', 'slug')) {
            $schoolRow['slug'] = $prefix.'-school-'.$suffix;
        }
        if (Schema::hasColumn('schools', 'is_active')) {
            $schoolRow['is_active'] = false;
        }
        DB::table('schools')->insert($schoolRow);

        $sessionRow = [
            'id' => $sessionId,
            'school_id' => $schoolId,
            'name' => $prefix.' Session',
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

        return [$schoolId, $sessionId];
    }

    private function isUniqueConstraintFailure(QueryException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unique')
            || str_contains($msg, 'integrity constraint')
            || ($e->errorInfo[0] ?? null) === '23000'
            || ($e->errorInfo[1] ?? null) === 19;
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
