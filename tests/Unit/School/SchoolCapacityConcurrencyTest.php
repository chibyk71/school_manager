<?php

namespace Tests\Unit\School;

use App\Models\School;
use App\Services\School\SchoolCapacityService;
use App\Services\School\SchoolLifecycleService;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\EntitlementResolver;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Capacity enforcement: sequential limit on any driver; multi-process concurrency
 * only on mysql/mariadb/pgsql with pcntl.
 *
 * SCHOOL_CAPACITY_MYSQL_CI=1: use a focused MySQL schema bootstrap instead of
 * migrate:fresh. Full migrate is blocked by a legacy exams→schools FK ordering
 * issue outside this feature's scope. The bootstrap creates the real tables the
 * capacity path uses (schools Phase-1 shape + school_capacity_locks) without
 * disabling foreign-key checks and without rewriting production migrations.
 */
class SchoolCapacityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function connectionsToTransact(): array
    {
        // Concurrent multi-process test needs committed rows visible to child processes.
        if ($this->isConcurrentTestCase()) {
            return [];
        }

        return [config('database.default')];
    }

    /**
     * When CI flag is set, build only the schema required for capacity locking.
     * Avoids full migrate:fresh which fails on MySQL due to exams FK before schools.
     */
    protected function refreshTestDatabase()
    {
        if (filter_var(env('SCHOOL_CAPACITY_MYSQL_CI', false), FILTER_VALIDATE_BOOLEAN)) {
            if (! RefreshDatabaseState::$migrated) {
                $this->bootstrapSchoolCapacityMysqlSchema();
                RefreshDatabaseState::$migrated = true;
            }

            // Only wrap in a transaction when connectionsToTransact is non-empty
            // (sequential tests). Concurrent test uses [] so children see commits.
            $this->beginDatabaseTransaction();

            return;
        }

        parent::refreshTestDatabase();
    }

    /**
     * Focused MySQL schema for Gate B capacity verification only.
     * Mirrors production schools columns used by createDraft + capacity lock.
     */
    private function bootstrapSchoolCapacityMysqlSchema(): void
    {
        Schema::dropIfExists('school_capacity_locks');
        Schema::dropIfExists('schools');

        Schema::create('schools', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone_one')->nullable();
            $table->string('phone_two')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            $table->string('motto')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->string('state')->default('draft')->index();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

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

        // Concurrent test skips on sqlite before needing schools.
        if ($this->isConcurrentTestCase() && ! $this->supportsProductionConcurrency()) {
            return;
        }

        $this->assertTrue(
            Schema::hasTable('schools'),
            'schools table missing after RefreshDatabase — migrations did not complete.'
        );

        if ($this->isConcurrentTestCase()) {
            $this->purgeAllSchoolsSafely();
        }

        $this->bindCapacityLimit(2);
    }

    private function isConcurrentTestCase(): bool
    {
        return $this->name() === 'test_concurrent_create_draft_cannot_exceed_limit';
    }

    private function supportsProductionConcurrency(): bool
    {
        $driver = DB::connection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)
            && function_exists('pcntl_fork');
    }

    private function purgeAllSchoolsSafely(): void
    {
        if (! Schema::hasTable('schools')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            try {
                Schema::disableForeignKeyConstraints();
                Schema::dropIfExists('terms_old_phase3');
                DB::table('schools')->delete();
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return;
        }

        School::query()->forceDelete();
    }

    private function bindCapacityLimit(int $limit): void
    {
        $this->app->instance(
            DefaultEntitlementProfile::class,
            new DefaultEntitlementProfile([
                DefaultEntitlementProfile::SCHOOLS_MAX_COUNT_KEY => $limit,
            ])
        );

        $this->app->forgetInstance(SchoolCapacityService::class);
        $this->app->forgetInstance(SchoolLifecycleService::class);
        $this->app->forgetInstance(EntitlementResolver::class);
        $this->app->forgetInstance(CapacityEvaluator::class);
    }

    public function test_create_draft_respects_capacity_limit(): void
    {
        $lifecycle = app(SchoolLifecycleService::class);

        $lifecycle->createDraft(['name' => 'One']);
        $lifecycle->createDraft(['name' => 'Two']);

        $this->expectException(InsufficientCapacityException::class);
        $lifecycle->createDraft(['name' => 'Three']);
    }

    public function test_concurrent_create_draft_cannot_exceed_limit(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->markTestSkipped(
                "Concurrent capacity verification requires mysql/mariadb/pgsql; current driver is {$driver}."
            );
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped(
                'pcntl extension required for multi-process concurrency verification on '.$driver
            );
        }

        // Dedicated CI job must never silently skip when the environment is wrong.
        if (filter_var(env('SCHOOL_CAPACITY_MYSQL_CI', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->assertContains($driver, ['mysql', 'mariadb', 'pgsql']);
            $this->assertTrue(function_exists('pcntl_fork'), 'pcntl_fork required in SCHOOL_CAPACITY_MYSQL_CI');
        }

        $this->assertTrue(Schema::hasTable('schools'), 'schools required for concurrency test');
        $this->purgeAllSchoolsSafely();
        $this->bindCapacityLimit(2);

        // Seed is committed (connectionsToTransact is empty for this method).
        app(SchoolLifecycleService::class)->createDraft(['name' => 'Seed-Committed']);
        $this->assertSame(1, School::query()->countedForCapacity()->count());

        $attempts = 4;
        $children = [];
        for ($i = 0; $i < $attempts; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                try {
                    DB::purge();
                    DB::reconnect();
                    $this->refreshApplication();
                    $this->bindCapacityLimit(2);
                    app(SchoolLifecycleService::class)->createDraft([
                        'name' => 'Concurrent-'.$i.'-'.getmypid(),
                    ]);
                    exit(0);
                } catch (InsufficientCapacityException) {
                    exit(2);
                } catch (\Throwable $e) {
                    fwrite(STDERR, $e->getMessage()."\n");
                    exit(1);
                }
            }
            $children[] = $pid;
        }

        $success = 0;
        $rejected = 0;
        foreach ($children as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            $code = pcntl_wexitstatus($status);
            if ($code === 0) {
                $success++;
            } elseif ($code === 2) {
                $rejected++;
            } else {
                $this->fail("Child {$pid} exited with unexpected code {$code}");
            }
        }

        DB::reconnect();
        $this->assertSame(1, $success, 'Exactly one concurrent create should succeed at remaining capacity 1');
        $this->assertSame($attempts - 1, $rejected);
        $this->assertSame(2, School::query()->countedForCapacity()->count());
    }
}
