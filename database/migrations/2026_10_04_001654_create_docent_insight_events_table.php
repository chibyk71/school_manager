<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docent_insight_events', function (Blueprint $table): void {
            $table->id();
            $table->string('site')->default('docs')->index();
            $table->uuid('event_id')->unique();
            $table->string('category', 20)->index();
            $table->string('event', 40)->index();
            $table->string('surface', 16);
            $table->string('page_slug')->nullable()->index();
            $table->string('query', 500)->nullable();
            $table->uuid('search_id')->nullable()->index();
            $table->string('reference_id', 64)->nullable()->index();
            $table->string('target_slug')->nullable();
            $table->unsignedSmallInteger('result_count')->nullable();
            $table->json('result_slugs')->nullable();
            $table->string('status', 20)->nullable();
            $table->json('citations')->nullable();
            $table->string('feedback', 4)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docent_insight_events');
    }
};
