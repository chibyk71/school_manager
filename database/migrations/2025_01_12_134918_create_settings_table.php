<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: Settings persistence uses oi-lab/oi-laravel-settings.
 * Legacy morph-based ruangdeveloper schema retired; this migration establishes
 * the OI table shape. Package migration is a no-op when the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            // If a morph-based legacy table exists from older installs, rebuild.
            // Project is pre-production; destructive reset is intentional for Phase 1.
            Schema::drop('settings');
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
