<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Configuration\EventType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('color')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        // Seed initial event types
        $eventTypes = [
            ['name' => 'Meeting', 'description' => 'School meetings and assemblies', 'color' => '#4CAF50'],
            ['name' => 'Holiday', 'description' => 'Public or school holidays', 'color' => '#FF9800'],
            ['name' => 'Examination', 'description' => 'School examinations', 'color' => '#F44336'],
            ['name' => 'Sports', 'description' => 'Sports events and competitions', 'color' => '#2196F3'],
            ['name' => 'Workshop', 'description' => 'Training or professional development', 'color' => '#9C27B0'],
        ];

        foreach ($eventTypes as $eventType) {
            EventType::create($eventType);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
