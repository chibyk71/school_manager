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

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->dropScopedNameUniquenessIndexes();

        // Restore global unique(name) only when safe (no duplicate names across scopes).
        $driver = Schema::getConnection()->getDriverName();
        $duplicates = DB::table('roles')
            ->select('name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates === 0) {
            if (in_array($driver, ['sqlite', 'pgsql'], true)) {
                try {
                    Schema::table('roles', function (Blueprint $table) {
                        $table->unique('name', 'roles_name_unique');
                    });
                } catch (\Throwable) {
                    // Index may already exist or name differs
                }
            } else {
                try {
                    Schema::table('roles', function (Blueprint $table) {
                        $table->unique('name', 'roles_name_unique');
                    });
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

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

        // SQLite: table rebuild style drop via doctrine-less approach
        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS roles_name_unique');
            } catch (\Throwable) {
                // ignore
            }
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
            // Map NULL school_id → sentinel so UNIQUE participates for tenant rows.
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

        // Unknown engine: best-effort composite unique (does not fully protect NULL tenant rows).
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
            } catch (\Throwable) {
                // ignore
            }
            try {
                DB::statement('DROP INDEX IF EXISTS roles_school_name_unique');
            } catch (\Throwable) {
                // ignore
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('DROP INDEX roles_ownership_name_unique ON roles');
            } catch (\Throwable) {
                // ignore
            }
            if (Schema::hasColumn('roles', 'ownership_scope')) {
                try {
                    DB::statement('ALTER TABLE roles DROP COLUMN ownership_scope');
                } catch (\Throwable) {
                    // ignore
                }
            }

            return;
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_school_name_unique');
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
