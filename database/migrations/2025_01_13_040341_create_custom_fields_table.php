<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->json('rules')->nullable();
            $table->json('classes')->nullable();
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->string('hint')->nullable();
            $table->integer('sort')->default(0)->index();
            $table->string('category')->nullable()->index();
            $table->json('extra_attributes')->nullable();
            $table->json('field_options')->nullable();
            $table->string('cast_as')->nullable();
            $table->boolean('has_options')->default(false);
            $table->string('model_type')->index();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'model_type', 'school_id'], 'custom_fields_unique');
        });

        Schema::create('custom_field_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
            $table->uuidMorphs('model');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('custom_field_responses');
        Schema::dropIfExists('custom_fields');
    }
};
