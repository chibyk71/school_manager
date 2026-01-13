<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Creates two main tables:
     * 1. custom_fields     → stores the definition / blueprint of each custom field
     * 2. custom_field_responses → stores the actual values filled in for each entity (student, teacher, etc.)
     *
     * Important design decisions:
     * • school_id is nullable → allows global (tenant-wide) presets when NULL
     * • unique constraint on (name, model_type, school_id) → prevents duplicate field names in the same scope
     * • json columns for rules, options, extra_attributes → flexible without constant schema changes
     * • softDeletes → allows "trash" functionality and potential restore
     * • value in responses is text → can store strings, json, media IDs, etc.
     */
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // Table: custom_fields (field definitions / blueprints)
        // ──────────────────────────────────────────────────────────────
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            // Core identity of the field
            $table->string('name')->index();                    // snake_case unique key, e.g. emergency_contact_phone
            $table->string('label')->nullable();                // Human-readable name shown in forms, e.g. "Emergency Phone"
            $table->string('placeholder')->nullable();          // Form placeholder text
            $table->string('field_type');                       // text, date, file, select, etc. (controlled by CustomFieldType class)

            // Scoping & ownership
            $table->string('model_type')->index();              // Eloquent model class or alias-resolved class, e.g. App\Models\Student
            $table->foreignUuid('school_id')->nullable()
                ->constrained('schools')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();                            // NULL = global/tenant preset, filled = school override

            // Flexible / extensible data
            $table->json('rules')->nullable();                  // validation rules array, e.g. ["required", "max:255"]
            $table->json('options')->nullable();                // for select/radio/checkbox → array of choices
            $table->json('extra_attributes')->nullable();       // future: custom metadata, styling, etc.
            $table->json('field_options')->nullable();          // additional config per field type

            // UI & UX helpers
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();            // longer explanation, shown as tooltip/help text
            $table->string('hint')->nullable();                 // short helper text under field
            $table->string('category')->nullable()->index();    // group fields, e.g. "emergency_contact", "medical"
            $table->integer('sort')->default(0)->index();       // display order in forms

            // Future/advanced features (added later but columns here for one migration)
            $table->string('cast_as')->nullable();              // e.g. 'integer', 'boolean', 'array' — for value casting
            $table->boolean('has_options')->default(false);     // quick flag for types that need options

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate names in same scope
            $table->unique(['name', 'model_type', 'school_id'], 'custom_fields_unique_name_scope');
        });

        // ──────────────────────────────────────────────────────────────
        // Table: custom_field_responses (actual values per record)
        // ──────────────────────────────────────────────────────────────
        Schema::create('custom_field_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('custom_field_id')
                ->constrained('custom_fields')
                ->cascadeOnDelete();                            // if field is deleted → remove its values

            // Polymorphic: links to any model (Student, Teacher, Staff, etc.)
            $table->uuidMorphs('model');                        // model_type + model_id

            $table->text('value')->nullable();                  // the actual entered value
            // can be: string, json (multi-select), media ID, etc.

            $table->timestamps();

            // Performance: speed up lookups for a particular entity
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_responses');
        Schema::dropIfExists('custom_fields');
    }
};
