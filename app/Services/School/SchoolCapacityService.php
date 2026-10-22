<?php

namespace App\Services\School;

use App\Entitlements\Definitions\SchoolManagementFeatures;
use App\Models\School;
use App\States\School\Active;
use App\States\School\Draft;
use App\States\School\Ready;
use App\States\School\Suspended;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tenant-scoped capacity enforcement for schools.max_count.
 *
 * Counted states: Draft, Ready, Active, Suspended.
 * Excluded: Archived, soft-deleted.
 *
 * Concurrency: every capacity-affecting mutation must run inside
 * {@see withCapacityLock()} so concurrent creates/restores/recoveries
 * cannot exceed the effective limit.
 *
 * Lock order: capacity lock row first, then any school row locks.
 */
final class SchoolCapacityService
{
    public const LOCK_SCOPE = 'tenant';

    public function __construct(
        private readonly CapacityEvaluator $capacity,
    ) {}

    /**
     * States that consume capacity.
     *
     * @return list<string>
     */
    public static function countedStateNames(): array
    {
        return [
            Draft::$name,
            Ready::$name,
            Active::$name,
            Suspended::$name,
        ];
    }

    /**
     * Current usage (non-archived, non-trashed schools in counted states).
     */
    public function currentUsage(): int
    {
        return (int) School::query()
            ->whereIn('state', self::countedStateNames())
            ->count();
    }

    public function limit(): int
    {
        return $this->capacity->limit(SchoolManagementFeatures::MAX_COUNT_KEY);
    }

    public function available(): int
    {
        return $this->capacity->available(
            SchoolManagementFeatures::MAX_COUNT_KEY,
            $this->currentUsage(),
        );
    }

    /**
     * Assert that requesting $delta additional counted schools is within limit.
     *
     * @throws InsufficientCapacityException
     */
    public function assertCanConsume(int $delta = 1): void
    {
        if ($delta <= 0) {
            return;
        }

        $this->capacity->assertWithinLimit(
            SchoolManagementFeatures::MAX_COUNT_KEY,
            $delta,
            $this->currentUsage(),
        );
    }

    /**
     * Run $callback inside a transaction with the tenant capacity lock held.
     *
     * The lock row is selected FOR UPDATE so concurrent capacity mutations serialize.
     * Works on MySQL/MariaDB/PostgreSQL. SQLite serializes writes at the DB level;
     * tests should still exercise the path, but production verification requires
     * the supported production engine.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function withCapacityLock(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            $this->acquireLock();

            return $callback();
        });
    }

    /**
     * Acquire the shared capacity lock (must be called inside an open transaction).
     */
    public function acquireLock(): void
    {
        $locked = DB::table('school_capacity_locks')
            ->where('scope_key', self::LOCK_SCOPE)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            // Self-heal if migration seed was skipped (should not happen in production).
            DB::table('school_capacity_locks')->insert([
                'scope_key' => self::LOCK_SCOPE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked = DB::table('school_capacity_locks')
                ->where('scope_key', self::LOCK_SCOPE)
                ->lockForUpdate()
                ->first();
        }

        if ($locked === null) {
            throw new RuntimeException('Unable to acquire school capacity lock.');
        }
    }
}
