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
 *
 * Orphan section IDs:
 *   Rows whose school_section_id does not resolve to a school_sections row
 *   receive school_id = null (not deleted) so operators can inspect them.
 *
 * Rollback:
 *   Best-effort reverse map via the first section of each school. Exact
 *   original section scope cannot always be recovered.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migratePivot('role_user', 'role_id');
        $this->migratePivot('permission_user', 'permission_id');
    }

    public function down(): void
    {
        $this->rollbackPivot('role_user', 'role_id');
        $this->rollbackPivot('permission_user', 'permission_id');
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
            $this->deduplicateBySchoolScope($table, $objectKey);
        }

        if ($hadSectionColumn) {
            $this->dropIndexSafe($table, $table === 'permission_user'
                ? 'permission_user_unique'
                : null);
            $this->dropIndexSafe($table, "{$table}_user_id_role_id_user_type_school_section_id_unique");
            $this->dropIndexSafe($table, "{$table}_user_id_permission_id_user_type_school_section_id_unique");
            $this->dropForeignSafe($table, 'school_section_id');

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('school_section_id');
            });
        }

        $this->dropForeignSafe($table, 'school_id');
        $this->dropIndexSafe($table, $table === 'permission_user'
            ? 'permission_user_unique'
            : "{$table}_user_role_type_school_unique");
        $this->dropIndexSafe($table, "{$table}_user_id_{$objectKey}_user_type_school_id_unique");

        Schema::table($table, function (Blueprint $blueprint) use ($table, $objectKey) {
            if (Schema::hasTable('schools')) {
                $blueprint->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            }

            $uniqueName = $table === 'permission_user'
                ? 'permission_user_unique'
                : "{$table}_user_role_type_school_unique";

            $blueprint->unique(
                ['user_id', $objectKey, 'user_type', 'school_id'],
                $uniqueName
            );
        });
    }

    private function rollbackPivot(string $table, string $objectKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'school_id')) {
            return;
        }

        if (! Schema::hasColumn($table, 'school_section_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('school_section_id')->nullable();
            });
        }

        $rows = DB::table($table)
            ->whereNotNull('school_id')
            ->get();

        foreach ($rows as $row) {
            $sectionId = DB::table('school_sections')
                ->where('school_id', $row->school_id)
                ->orderBy('sort_order')
                ->value('id');

            DB::table($table)
                ->where('user_id', $row->user_id)
                ->where($objectKey, $row->{$objectKey})
                ->where('user_type', $row->user_type)
                ->where('school_id', $row->school_id)
                ->update(['school_section_id' => $sectionId]);
        }

        $this->dropForeignSafe($table, 'school_id');
        $this->dropIndexSafe($table, $table === 'permission_user'
            ? 'permission_user_unique'
            : "{$table}_user_role_type_school_unique");
        $this->dropIndexSafe($table, "{$table}_user_id_{$objectKey}_user_type_school_id_unique");

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('school_id');
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table, $objectKey) {
            if (Schema::hasTable('school_sections')) {
                $blueprint->foreign('school_section_id')
                    ->references('id')
                    ->on('school_sections')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            }

            $uniqueName = $table === 'permission_user'
                ? 'permission_user_unique'
                : "{$table}_user_role_type_section_unique";

            $blueprint->unique(
                ['user_id', $objectKey, 'user_type', 'school_section_id'],
                $uniqueName
            );
        });
    }

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

        $rows = DB::table($table)
            ->whereNotNull('school_section_id')
            ->whereNull('school_id')
            ->get();

        foreach ($rows as $row) {
            $schoolId = DB::table('school_sections')
                ->where('id', $row->school_section_id)
                ->value('school_id');

            DB::table($table)
                ->where('user_id', $row->user_id)
                ->where($objectKey, $row->{$objectKey})
                ->where('user_type', $row->user_type)
                ->where('school_section_id', $row->school_section_id)
                ->update(['school_id' => $schoolId]);
        }
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
