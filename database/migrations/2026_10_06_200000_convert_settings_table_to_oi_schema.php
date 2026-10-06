<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: ensure the settings table is the OI schema.
 *
 * Deterministic path for development databases that already recorded the
 * original morph-based 2025_01_12_134918 migration:
 *   - morph schema (model_type present) → drop + recreate OI shape
 *   - OI schema already (scope present) → no-op
 *   - table missing → create OI shape
 *
 * Pre-production only: morph data is not migrated; dual-store is not retained.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            $this->createOiSchema();

            return;
        }

        if (Schema::hasColumn('settings', 'model_type') || Schema::hasColumn('settings', 'model_id')) {
            Schema::drop('settings');
            $this->createOiSchema();

            return;
        }

        // Already OI-shaped (scope column) — leave intact.
        if (Schema::hasColumn('settings', 'scope')) {
            return;
        }

        // Unknown shape: rebuild to OI.
        Schema::drop('settings');
        $this->createOiSchema();
    }

    public function down(): void
    {
        // Irreversible in pre-production; dropping leaves no morph restoration.
        Schema::dropIfExists('settings');
    }

    private function createOiSchema(): void
    {
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
};
