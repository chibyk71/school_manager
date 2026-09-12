<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: prevent cascade-delete of academic terms when a session is deleted.
 *
 * Original create migration uses cascadeOnDelete(); this later migration changes
 * the live schema to restrictOnDelete() without editing historical migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropTermsSessionForeignKey();

        Schema::table('terms', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->dropTermsSessionForeignKey();

        Schema::table('terms', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    private function dropTermsSessionForeignKey(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('terms', function (Blueprint $table) {
                $table->dropForeign(['academic_session_id']);
            });

            return;
        }

        $constraint = $this->resolveTermsSessionConstraintName();

        if ($constraint) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE terms DROP FOREIGN KEY `{$constraint}`");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE terms DROP CONSTRAINT \"{$constraint}\"");
            } else {
                Schema::table('terms', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint);
                });
            }
        } else {
            Schema::table('terms', function (Blueprint $table) {
                $table->dropForeign(['academic_session_id']);
            });
        }
    }

    private function resolveTermsSessionConstraintName(): ?string
    {
        $driver = Schema::getConnection()->getDriverName();
        $database = Schema::getConnection()->getDatabaseName();

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne(
                    'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                       AND REFERENCED_TABLE_NAME IS NOT NULL
                     LIMIT 1',
                    [$database, 'terms', 'academic_session_id']
                );

                return $row->name ?? null;
            }

            if ($driver === 'pgsql') {
                $row = DB::selectOne(
                    "SELECT tc.constraint_name AS name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu
                       ON tc.constraint_name = kcu.constraint_name
                      AND tc.table_schema = kcu.table_schema
                     WHERE tc.constraint_type = 'FOREIGN KEY'
                       AND tc.table_name = 'terms'
                       AND kcu.column_name = 'academic_session_id'
                     LIMIT 1"
                );

                return $row->name ?? null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
};
