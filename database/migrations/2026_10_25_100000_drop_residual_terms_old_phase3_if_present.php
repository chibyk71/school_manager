<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net: remove leftover terms_old_phase3 from interrupted Phase 3 SQLite rebuilds.
 * That residual table/FK bookkeeping can make DELETE FROM schools fail with
 * "no such table: main.terms_old_phase3" on subsequent test or migrate:fresh runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terms_old_phase3')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = OFF');
            } catch (Throwable) {
            }
        }

        Schema::dropIfExists('terms_old_phase3');

        if ($driver === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = ON');
            } catch (Throwable) {
            }
        }
    }

    public function down(): void
    {
        // irreversible safety cleanup
    }
};
