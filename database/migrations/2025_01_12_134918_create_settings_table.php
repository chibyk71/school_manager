<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings table for oi-lab/oi-laravel-settings (Phase 1).
 *
 * Pre-production: the former morph-based ruangdeveloper schema is retired.
 * Fresh installs create the OI schema directly.
 * Existing development databases that already ran the old morph migration are
 * converted by 2026_10_06_200000_convert_settings_table_to_oi_schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->nullable()->index();
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('string');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
