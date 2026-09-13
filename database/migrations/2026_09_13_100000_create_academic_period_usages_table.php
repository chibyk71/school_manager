<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: Academic period dependency registry.
 *
 * Represents current dependency state only (no soft deletes, no history).
 * A resource declares at most one row: (school_id, resource_type, resource_id).
 * Term-level rows carry both academic_session_id and term_id; session-level
 * rows leave term_id null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('academic_period_usages')) {
            return;
        }

        Schema::create('academic_period_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->foreignUuid('term_id')
                ->nullable()
                ->constrained('terms')
                ->restrictOnDelete();

            // Morph: class name or morph map alias; resource_id is string to support UUID + integer PKs.
            $table->string('resource_type', 191);
            $table->string('resource_id', 36);

            $table->timestamps();

            // One dependency registration per resource globally within a school.
            $table->unique(
                ['school_id', 'resource_type', 'resource_id'],
                'apu_school_resource_unique'
            );

            // Session dependency lookup (includes term-level rows under the session).
            $table->index(
                ['school_id', 'academic_session_id'],
                'apu_school_session_idx'
            );

            // Term dependency lookup.
            $table->index(
                ['school_id', 'term_id'],
                'apu_school_term_idx'
            );

            // Reverse lookup by resource.
            $table->index(
                ['resource_type', 'resource_id'],
                'apu_resource_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_period_usages');
    }
};
