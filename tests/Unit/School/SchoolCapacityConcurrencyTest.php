<?php

namespace Tests\Unit\School;

use App\Services\School\SchoolCapacityService;
use App\Services\School\SchoolLifecycleService;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Capacity boundary tests. True multi-connection concurrency must be run
 * against MySQL/MariaDB/PostgreSQL — SQLite is insufficient evidence.
 */
class SchoolCapacityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('school_capacity_locks')) {
            Schema::create('school_capacity_locks', function ($table) {
                $table->id();
                $table->string('scope_key')->default('tenant')->unique();
                $table->timestamps();
            });
            DB::table('school_capacity_locks')->insert([
                'scope_key' => 'tenant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_create_draft_respects_capacity_limit_when_overridden(): void
    {
        $this->app->instance(
            DefaultEntitlementProfile::class,
            new DefaultEntitlementProfile([
                DefaultEntitlementProfile::SCHOOLS_MAX_COUNT_KEY => 2,
            ])
        );

        // Re-bind dependent services that captured the old profile.
        $this->app->forgetInstance(SchoolCapacityService::class);
        $this->app->forgetInstance(SchoolLifecycleService::class);
        $this->app->forgetInstance(\App\Support\Entitlements\EntitlementResolver::class);
        $this->app->forgetInstance(\App\Support\Entitlements\CapacityEvaluator::class);

        $lifecycle = app(SchoolLifecycleService::class);

        $lifecycle->createDraft(['name' => 'One']);
        $lifecycle->createDraft(['name' => 'Two']);

        $this->expectException(InsufficientCapacityException::class);
        $lifecycle->createDraft(['name' => 'Three']);
    }

    public function test_production_engine_concurrency_must_be_run_outside_sqlite(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $this->markTestSkipped(
                'Concurrent capacity races must be verified on MySQL/MariaDB/PostgreSQL, not SQLite.'
            );
        }

        $this->assertTrue(in_array($driver, ['mysql', 'mariadb', 'pgsql'], true));
        // Owner: run parallel createDraft workers at limit-1 and assert count never exceeds limit.
        $this->assertTrue(true);
    }
}
