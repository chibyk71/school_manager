<?php

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared SQLite schema maintenance (not School product).
 *
 * Covers: staff indexes, terms indexes after phase3 rebuild path, enrollment indexes.
 */
class SqliteSchemaMaintenanceTest extends TestCase
{
    use RefreshDatabase;

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
