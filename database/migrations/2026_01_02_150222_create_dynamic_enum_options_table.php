<?php

/**
 * Dynamic Enum Phase 1 — normalized option rows.
 *
 * Options are first-class database rows owned by a DynamicEnum definition.
 * Within one definition, (dynamic_enum_id, value) is unique.
 * The same value may exist under different definitions.
 *
 * Defaults:
 *   is_active   = true
 *   is_required = false
 *   sort_order  = 0
 *
 * Cascade on definition delete keeps options from becoming orphans.
 * Soft-delete is intentionally not used; lifecycle uses is_active (Phase 2).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_enum_options', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('dynamic_enum_id')
                ->constrained('dynamic_enums')
                ->cascadeOnDelete();

            // Stable machine identity within the definition
            $table->string('value');

            // Human-facing presentation
            $table->string('label');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);

            // Optional presentation metadata
            $table->string('color')->nullable();
            $table->string('icon')->nullable();

            $table->timestamps();

            $table->unique(['dynamic_enum_id', 'value'], 'dynamic_enum_options_enum_value_unique');
            $table->index(['dynamic_enum_id', 'sort_order'], 'dynamic_enum_options_enum_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_enum_options');
    }
};
