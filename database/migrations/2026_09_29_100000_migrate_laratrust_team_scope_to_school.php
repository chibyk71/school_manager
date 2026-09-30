<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Permission / Authorization Foundation
 *
 * Migrates Laratrust team scope from SchoolSection → School.
 *
 * Before:
 *   teams table          = school_sections
 *   team foreign key     = school_section_id  (on role_user, permission_user)
 *   Team model           = SchoolSection
 *
 * After:
 *   teams table          = schools
 *   team foreign key     = school_id          (on role_user, permission_user)
 *   Team model           = School
 *
 * Data migration:
 *   For each pivot row with a non-null school_section_id, resolve the
 *   parent School via school_sections.school_id and write that UUID into
 *   the new school_id column. Rows with null team remain null (tenant /
 *   global assignments).
 *
 * Deduplication:
 *   Multiple section-scoped assignments for the same (user, role/permission,
 *   user_type) within one school collapse to a single school-scoped row.
 *   Only rows that successfully resolved to a non-null school_id participate.
 *
 * Orphan section IDs (authorization-sensitive):
 *   Rows whose school_section_id is non-null but does not resolve to a
 *   school_sections row MUST NOT become school_id = NULL. That would
 *   escalate a formerly scoped assignment into a tenant/global grant.
 *   The migration fails closed when any such unresolved rows remain after
 *   the copy step. Operators must repair or delete those pivots before
 *   re-running the migration.
 *
 * Rollback:
 *   Schema reversal only is not sufficient to reconstruct original section
 *   scope. Authorization pivots cannot safely invent a section_id.
 *   down() therefore refuses to run and documents the limitation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migratePivot('role_user', 'role_id');
        $this->migratePivot('permission_user', 'permission_id');
    }

    /**
     * Authorization scope cannot be reconstructed from school_id alone.
     * Mapping every school-scoped row to "the first section of that school"
     * would invent incorrect section-scoped grants and is refused.
     *
     * @throws \RuntimeException always — intentional irreversible migration
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Cannot safely roll back Phase 1 Laratrust team-scope migration: '
            .'original school_section_id values cannot be reconstructed from school_id. '
            .'Restore from a pre-migration backup if rollback is required.'
        );
    }

    private function migratePivot(string $table, string $objectKey): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $hadSectionColumn = Schema::hasColumn($table, 'school_section_id');

        if (! Schema::hasColumn($table, 'school_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('school_id')->nullable();
            });
        }

        if ($hadSectionColumn) {
            $this->copySectionScopeToSchool($table, $objectKey);
            $this->assertNoUnresolvedSectionAssignments($table);
            $this->deduplicateBySchoolScope($table, $objectKey);
        }

        if ($hadSectionColumn) {
            $this->dropIndexSafe($table, $table === 'permission_user'
                ? 'permission_user_unique'
                : null);
            $this->dropIndexSafe($table, "{$table}_user_id_role_id_user_type_school_section_id_unique");
            $this->dropIndexSafe($table, "{$table}_user_id_permission_id_user_type_school_section_id_unique");
            $this->dropForeignSafe($table, 'school_section_id');

            // SQLite cannot drop a column that participates in a unique index —
            // rebuild the table when needed; other drivers can drop the column.
            $this->dropSectionColumn($table, $objectKey);
        }

        $this->dropForeignSafe($table, 'school_id');
        $this->dropIndexSafe($table, $table === 'permission_user'
            ? 'permission_user_unique'
            : "{$table}_user_role_type_school_unique");
        $this->dropIndexSafe($table, "{$table}_user_id_{$objectKey}_user_type_school_id_unique");

        Schema::table($table, function (Blueprint $blueprint) use ($table, $objectKey) {
            if (Schema::hasTable('schools')) {
                try {
                    $blueprint->foreign('school_id')
                        ->references('id')
                        ->on('schools')
                        ->onUpdate('cascade')
                        ->onDelete('cascade');
                } catch (\Throwable) {
                    // SQLite / already-present FK
                }
            }

            $uniqueName = $table === 'permission_user'
                ? 'permission_user_unique'
                : "{$table}_user_role_type_school_unique";

            try {
                $blueprint->unique(
                    ['user_id', $objectKey, 'user_type', 'school_id'],
                    $uniqueName
                );
            } catch (\Throwable) {
                // Unique may already exist after rebuild
            }
        });
    }

    /**
     * Resolve school_section_id → school_id for every pivot row that can.
     * Unresolved non-null section IDs are left with school_id still NULL;
     * assertNoUnresolvedSectionAssignments() then fails the migration.
     */
    private function copySectionScopeToSchool(string $table, string $objectKey): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                UPDATE `{$table}` AS pivot
                INNER JOIN school_sections AS ss ON ss.id = pivot.school_section_id
                SET pivot.school_id = ss.school_id
                WHERE pivot.school_section_id IS NOT NULL
                  AND pivot.school_id IS NULL
            ");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("
                UPDATE {$table} AS pivot
                SET school_id = ss.school_id
                FROM school_sections AS ss
                WHERE ss.id = pivot.school_section_id
                  AND pivot.school_section_id IS NOT NULL
                  AND pivot.school_id IS NULL
            ");

            return;
        }

        // SQLite and other drivers: row-by-row
        $rows = DB::table($table)
            ->whereNotNull('school_section_id')
            ->whereNull('school_id')
            ->get();

        foreach ($rows as $row) {
            $schoolId = DB::table('school_sections')
                ->where('id', $row->school_section_id)
                ->value('school_id');

            if ($schoolId === null) {
                // Leave school_id NULL; assert step will fail the migration.
                continue;
            }

            DB::table($table)
                ->where('user_id', $row->user_id)
                ->where($objectKey, $row->{$objectKey})
                ->where('user_type', $row->user_type)
                ->where('school_section_id', $row->school_section_id)
                ->update(['school_id' => $schoolId]);
        }
    }

    /**
     * Fail closed: any non-null school_section_id that did not resolve to a
     * school must not become a global (school_id = NULL) authorization grant.
     *
     * @throws \RuntimeException
     */
    private function assertNoUnresolvedSectionAssignments(string $table): void
    {
        if (! Schema::hasColumn($table, 'school_section_id')) {
            return;
        }

        $orphans = DB::table($table)
            ->whereNotNull('school_section_id')
            ->whereNull('school_id')
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $sample = $orphans->take(5)->map(function ($row) {
            return sprintf(
                'user_id=%s section_id=%s',
                $row->user_id ?? '?',
                $row->school_section_id ?? '?'
            );
        })->implode('; ');

        throw new \RuntimeException(
            "Cannot migrate {$table}: {$orphans->count()} authorization row(s) have "
            .'school_section_id that does not resolve to a school_sections record. '
            .'Leaving them as school_id = NULL would escalate scoped grants to '
            .'tenant/global authorization. Repair or remove those pivots, then re-run. '
            ."Sample: {$sample}"
        );
    }

    private function deduplicateBySchoolScope(string $table, string $objectKey): void
    {
        $groups = DB::table($table)
            ->select('user_id', $objectKey, 'user_type', 'school_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id', $objectKey, 'user_type', 'school_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $query = DB::table($table)
                ->where('user_id', $group->user_id)
                ->where($objectKey, $group->{$objectKey})
                ->where('user_type', $group->user_type);

            if ($group->school_id === null) {
                $query->whereNull('school_id');
            } else {
                $query->where('school_id', $group->school_id);
            }

            $extras = $query->get()->slice(1);

            foreach ($extras as $extra) {
                $del = DB::table($table)
                    ->where('user_id', $extra->user_id)
                    ->where($objectKey, $extra->{$objectKey})
                    ->where('user_type', $extra->user_type);

                if (property_exists($extra, 'school_section_id') && $extra->school_section_id !== null) {
                    $del->where('school_section_id', $extra->school_section_id);
                } elseif (property_exists($extra, 'school_section_id')) {
                    $del->whereNull('school_section_id');
                }

                if ($extra->school_id === null) {
                    $del->whereNull('school_id');
                } else {
                    $del->where('school_id', $extra->school_id);
                }

                $del->limit(1)->delete();
            }
        }
    }

    /**
     * Drop school_section_id after data has been migrated. On SQLite, rebuild
     * the table because unique indexes block column drops.
     */
    private function dropSectionColumn(string $table, string $objectKey): void
    {
        if (! Schema::hasColumn($table, 'school_section_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table($table)->get();
            Schema::drop($table);

            Schema::create($table, function (Blueprint $blueprint) use ($table, $objectKey) {
                if ($objectKey === 'permission_id') {
                    $blueprint->unsignedBigInteger('permission_id');
                } else {
                    $blueprint->uuid($objectKey);
                }
                $blueprint->uuid('user_id');
                $blueprint->string('user_type');
                $blueprint->uuid('school_id')->nullable();

                $uniqueName = $table === 'permission_user'
                    ? 'permission_user_unique'
                    : "{$table}_user_role_type_school_unique";

                $blueprint->unique(
                    ['user_id', $objectKey, 'user_type', 'school_id'],
                    $uniqueName
                );
            });

            foreach ($rows as $row) {
                $payload = [
                    $objectKey => $row->{$objectKey},
                    'user_id' => $row->user_id,
                    'user_type' => $row->user_type,
                    'school_id' => $row->school_id,
                ];
                DB::table($table)->insert($payload);
            }

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('school_section_id');
        });
    }

    private function dropForeignSafe(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                });
            } catch (\Throwable) {
                // No FK present (common on SQLite without enforcement).
            }
        }
    }

    private function dropIndexSafe(string $table, ?string $indexName): void
    {
        if ($indexName === null) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropUnique($indexName);
            });
        } catch (\Throwable) {
            // Index may not exist under this name.
        }
    }
};
