<?php

/**
 * Permission Phase 2 — Role domain + scoped identity.
 *
 * Adds:
 *   - roles.disabled (boolean, default false)
 *   - UNIQUE(name, school_id) semantics for role identity
 *
 * Identity rules:
 *   - name is the stable role identity within an authorization scope
 *   - school_id = NULL  → tenant/global role
 *   - school_id = S     → school-local role
 *   - Same name may exist once per scope (tenant + each school)
 *
 * Uniqueness strategy (same as Dynamic Enum definitions):
 *   - SQLite / PostgreSQL: partial unique indexes
 *   - MySQL / MariaDB: STORED generated ownership_scope + unique index
 *
 * Removes the original global unique(name) constraint so scoped coexistence works.
 *
 * Does not implement inheritance, effective catalogue, or assignment workflows.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sentinel UUID used only for uniqueness of tenant (school_id IS NULL) rows.
     * Must never collide with a real schools.id.
     */
    private const TENANT_OWNERSHIP_SCOPE = '00000000-0000-0000-0000-000000000000';

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'disabled')) {
                $table->boolean('disabled')->default(false)->after('description');
            }
        });

        $this->dropGlobalNameUnique();
        $this->addScopedNameUniquenessIndexes();
    }

    /**
     * Roll back only when the previous global unique(name) invariant can be restored.
     *
     * If the same role name exists in more than one authorization scope, dropping
     * the Phase 2 scoped indexes would leave roles with no name uniqueness at all.
     * That is refused: fail closed before destroying scoped protection.
     *
     * Order:
     * 1. Detect cross-scope name duplicates
     * 2. Throw if restore of global unique(name) is impossible
     * 3. Drop scoped uniqueness indexes
     * 4. Restore roles.name uniqueness
     * 5. Drop disabled
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $duplicates = DB::table('roles')
            ->select('name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('name')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates->take(5)->map(function ($row) {
                return sprintf('%s (×%d)', $row->name, $row->cnt);
            })->implode(', ');

            throw new \RuntimeException(
                'Cannot roll back Phase 2 role domain migration: '
                .$duplicates->count().' role name(s) exist in more than one authorization scope. '
                .'Dropping scoped uniqueness would leave roles without a name uniqueness constraint. '
                .'Resolve or remove cross-scope duplicate names before rolling back. '
                ."Sample: {$sample}"
            );
        }

        $this->dropScopedNameUniquenessIndexes();
        $this->restoreGlobalNameUnique();

        if (Schema::hasColumn('roles', 'disabled')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('disabled');
            });
        }
    }

    /**
     * Drop the original global unique index on roles.name.
     */
    private function dropGlobalNameUnique(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $candidates = [
            'roles_name_unique',
            'roles_name_unique_index',
        ];

        foreach ($candidates as $indexName) {
            try {
                Schema::table('roles', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });

                return;
            } catch (\Throwable) {
                // try next name / approach
            }
        }

        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS roles_name_unique');
            } catch (\Throwable) {
                // ignore — may not exist on fresh partial-index-only schemas
            }
        }
    }

    /**
     * Restore the pre-Phase-2 global unique(name) constraint.
     * Caller must have already verified that no duplicate names exist.
     *
     * @throws \RuntimeException if the unique index cannot be created
     */
    private function restoreGlobalNameUnique(): void
    {
        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique('name', 'roles_name_unique');
            });
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Cannot roll back Phase 2 role domain migration: failed to restore '
                .'global unique constraint on roles.name. '
                .$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Enforce (name, school scope) identity at the database level.
     */
    private function addScopedNameUniquenessIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $nil = self::TENANT_OWNERSHIP_SCOPE;

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX roles_tenant_name_unique ON roles (name) WHERE school_id IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX roles_school_name_unique ON roles (school_id, name) WHERE school_id IS NOT NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (! Schema::hasColumn('roles', 'ownership_scope')) {
                DB::statement("
                    ALTER TABLE roles
                    ADD COLUMN ownership_scope CHAR(36)
                        CHARACTER SET ascii COLLATE ascii_bin
                        GENERATED ALWAYS AS (IFNULL(school_id, '{$nil}')) STORED
                        NOT NULL
                ");
            }

            DB::statement(
                'CREATE UNIQUE INDEX roles_ownership_name_unique ON roles (ownership_scope, `name`)'
            );

            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['school_id', 'name'], 'roles_school_name_unique');
        });
    }

    private function dropScopedNameUniquenessIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            try {
                DB::statement('DROP INDEX IF EXISTS roles_tenant_name_unique');
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Cannot drop roles_tenant_name_unique during Phase 2 rollback: '.$e->getMessage(),
                    0,
                    $e
                );
            }
            try {
                DB::statement('DROP INDEX IF EXISTS roles_school_name_unique');
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Cannot drop roles_school_name_unique during Phase 2 rollback: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('DROP INDEX roles_ownership_name_unique ON roles');
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Cannot drop roles_ownership_name_unique during Phase 2 rollback: '.$e->getMessage(),
                    0,
                    $e
                );
            }
            if (Schema::hasColumn('roles', 'ownership_scope')) {
                try {
                    DB::statement('ALTER TABLE roles DROP COLUMN ownership_scope');
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        'Cannot drop ownership_scope during Phase 2 rollback: '.$e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            return;
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_school_name_unique');
            });
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Cannot drop roles_school_name_unique during Phase 2 rollback: '.$e->getMessage(),
                0,
                $e
            );
        }
    }
};
