<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Intentionally no-op on the School Management branch.
 * Orphan staff index repair lives on fix/sqlite-schema-maintenance-clean.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — see fix/sqlite-schema-maintenance-clean
    }

    public function down(): void
    {
        // no-op
    }
};
