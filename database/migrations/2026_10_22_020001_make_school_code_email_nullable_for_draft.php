<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 Draft creation requires only a name. Code and email remain unique
 * when present, but must be nullable so incomplete Draft schools can be stored.
 *
 * Uniqueness of non-null values is preserved by the existing unique indexes
 * (MySQL/MariaDB allow multiple NULLs in a UNIQUE column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('type')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Down-migration may fail if null codes/emails exist.
        Schema::table('schools', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('type')->default('private')->nullable(false)->change();
        });
    }
};
