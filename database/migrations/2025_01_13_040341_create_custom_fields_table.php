<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->json('rules')->nullable();
            $table->json('classes')->nullable();
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->string('hint')->nullable();
            $table->integer('sort')->default(0);
            $table->string('category')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->json('field_options')->nullable();
            $table->string('cast_as')->nullable();
            $table->boolean('has_options')->default(0);
            $table->string('model_type');
            $table->foreignUuid('school_id')->references('schools')->cascadeOnDelete()->cascadeOnUpdate()->index()->nullable();
            $table->timestamps();
            $table->unique(['name', 'model_type', 'school_id'], 'custom_fields_unique_name_model_type_school_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('custom_fields');
    }
};
