<?php

namespace Tests\Unit\School;

use App\Models\School;
use App\Services\School\SchoolCapacityService;
use App\Services\School\SchoolLifecycleService;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Capacity enforcement under contention.
 *
 * Sequential lock-path tests run on any engine. True multi-connection
 * concurrency runs only on mysql/mariadb/pgsql when pcntl is available.
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

        $this->bindCapacityLimit(2);
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
        $this->app->forgetInstance(\App\Support\Entitlements\EntitlementResolver::class);
        $this->app->forgetInstance(\App\Support\Entitlements\CapacityEvaluator::class);
    }

    public function test_create_draft_respects_capacity_limit(): void
    {
        $lifecycle = app(SchoolLifecycleService::class);

        $lifecycle->createDraft(['name' => 'One']);
        $lifecycle->createDraft(['name' => 'Two']);

        $this->expectException(InsufficientCapacityException::class);
        $lifecycle->createDraft(['name' => 'Three']);
    }

    public function test_with_capacity_lock_serializes_usage_checks(): void
    {
        $capacity = app(SchoolCapacityService::class);
        $lifecycle = app(SchoolLifecycleService::class);

        $lifecycle->createDraft(['name' => 'A']);

        $capacity->withCapacityLock(function () use ($lifecycle) {
            $lifecycle->createDraft(['name' => 'B']);
            try {
                $lifecycle->createDraft(['name' => 'C']);
                $this->fail('Expected InsufficientCapacityException');
            } catch (InsufficientCapacityException) {
                // expected
            }
        });

        $this->assertSame(2, School::query()->countedForCapacity()->count());
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

        app(SchoolLifecycleService::class)->createDraft(['name' => 'Seed']);

        $children = [];
        $attempts = 4;
        for ($i = 0; $i < $attempts; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                DB::reconnect();
                $this->bindCapacityLimit(2);
                try {
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

        $this->assertSame(1, $success, 'Exactly one concurrent create should succeed at remaining capacity 1');
        $this->assertSame($attempts - 1, $rejected);
        $this->assertSame(2, School::query()->countedForCapacity()->count());
    }
}
