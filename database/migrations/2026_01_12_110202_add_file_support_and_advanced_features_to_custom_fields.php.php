<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds columns needed for:
     *  • File & image uploads (with constraints)
     *  • Conditional visibility rules (future)
     *  • Preset linking
     *  • Role-based visibility (future)
     *
     * All columns are nullable → safe to add to existing table
     */
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            // ─── File / Image support ───────────────────────────────────────
            $table->string('file_path')->nullable();                    // single file path (legacy / direct)
            $table->json('file_paths')->nullable();                     // multiple files (array of paths or media IDs)
            $table->string('file_type')->nullable();                    // 'single' | 'multiple'
            $table->unsignedInteger('max_file_size_kb')->nullable();    // e.g. 2048 = 2MB limit per field
            $table->json('allowed_extensions')->nullable();             // ['pdf','jpg','png']

            // ─── Conditional logic (future) ─────────────────────────────────
            $table->json('conditional_rules')->nullable();              // show/hide based on other fields

            // ─── Preset / template support ──────────────────────────────────
            $table->string('preset_key')->nullable();                   // e.g. 'medical_info', 'guardian_details'
            $table->boolean('is_preset')->default(false);               // marks seeded global defaults

            // ─── Visibility & access control (future) ───────────────────────
            $table->string('visibility_scope')->nullable();             // 'all', 'students_only', 'staff_only'
            $table->json('role_restrictions')->nullable();              // ['admin','teacher'] who can see/edit
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropColumn([
                'file_path',
                'file_paths',
                'file_type',
                'max_file_size_kb',
                'allowed_extensions',
                'conditional_rules',
                'preset_key',
                'is_preset',
                'visibility_scope',
                'role_restrictions',
            ]);
        });
    }
};
