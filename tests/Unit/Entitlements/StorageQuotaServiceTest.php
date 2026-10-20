<?php

namespace Tests\Unit\Entitlements;

use App\Entitlements\Definitions\FileManagerFeatures;
use App\Models\FileManager\StorageAccount;
use App\Models\FileManager\StorageReservation;
use App\Services\FileManager\StorageQuotaService;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\EntitlementRegistry;
use App\Support\Entitlements\EntitlementResolver;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Durable reservation / settlement / release behaviour.
 *
 * Uses focused schema (same approach as File Manager domain tests) to avoid
 * unrelated migration debt on SQLite.
 */
final class StorageQuotaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateQuotaSchema();
    }

    public function test_successful_reservation_below_limit(): void
    {
        $service = $this->serviceWithLimit(10_000);

        $reservation = $service->reserve(1_000);

        $this->assertSame(StorageReservation::STATE_PENDING, $reservation->state);
        $this->assertSame(1_000, $reservation->reserved_bytes);

        $snap = $service->snapshot();
        $this->assertSame(0, $snap['used_bytes']);
        $this->assertSame(1_000, $snap['reserved_bytes']);
        $this->assertSame(9_000, $snap['available']);
    }

    public function test_rejection_at_and_beyond_capacity(): void
    {
        $service = $this->serviceWithLimit(1_000);
        $service->reserve(1_000);

        $this->expectException(InsufficientCapacityException::class);
        $service->reserve(1);
    }

    public function test_accounts_existing_usage_plus_outstanding_reservations(): void
    {
        $service = $this->serviceWithLimit(5_000);
        $r1 = $service->reserve(2_000);
        $service->settle($r1, (string) Str::uuid(), 2_000);

        $service->reserve(2_000);

        $this->expectException(InsufficientCapacityException::class);
        $service->reserve(1_001);
    }

    public function test_idempotent_reservation_by_operation_id(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $op = (string) Str::uuid();

        $a = $service->reserve(500, $op);
        $b = $service->reserve(500, $op);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(500, $service->snapshot()['reserved_bytes']);
    }

    public function test_settlement_moves_reserved_to_used_without_double_count(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $reservation = $service->reserve(3_000);
        $fileId = (string) Str::uuid();

        $settled = $service->settle($reservation, $fileId, 2_500);
        $this->assertSame(StorageReservation::STATE_SETTLED, $settled->state);
        $this->assertSame(2_500, $settled->settled_bytes);

        $snap = $service->snapshot();
        $this->assertSame(2_500, $snap['used_bytes']);
        $this->assertSame(0, $snap['reserved_bytes']);

        // Idempotent settle with matching arguments
        $again = $service->settle($reservation, $fileId, 2_500);
        $this->assertSame(StorageReservation::STATE_SETTLED, $again->state);
        $this->assertSame(2_500, $service->snapshot()['used_bytes']);
    }

    public function test_settlement_rejects_conflicting_retry_arguments(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $reservation = $service->reserve(1_000);
        $fileId = (string) Str::uuid();
        $service->settle($reservation, $fileId, 1_000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicting settlement');
        $service->settle($reservation, $fileId, 999);
    }

    public function test_settlement_rejects_when_actual_exceeds_remaining_capacity(): void
    {
        $service = $this->serviceWithLimit(1_000);
        // Reserve 400; another reservation holds 500 → 100 free after this hold returns.
        $service->reserve(500);
        $r = $service->reserve(400);

        $this->expectException(InsufficientCapacityException::class);
        // Actual written size 600 would exceed limit once the 400 hold is returned
        // (used 0 + reserved 500 + 600 > 1000).
        $service->settle($r, (string) Str::uuid(), 600);
    }

    public function test_settlement_allows_actual_greater_than_reservation_when_capacity_permits(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $r = $service->reserve(100);
        $fileId = (string) Str::uuid();

        $settled = $service->settle($r, $fileId, 250);
        $this->assertSame(250, $settled->settled_bytes);
        $this->assertSame(250, $service->snapshot()['used_bytes']);
        $this->assertSame(0, $service->snapshot()['reserved_bytes']);
    }

    public function test_release_after_confirmed_cleanup(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $reservation = $service->reserve(4_000);

        $released = $service->release($reservation, 'cleanup confirmed');
        $this->assertSame(StorageReservation::STATE_RELEASED, $released->state);

        $snap = $service->snapshot();
        $this->assertSame(0, $snap['used_bytes']);
        $this->assertSame(0, $snap['reserved_bytes']);
        $this->assertSame(10_000, $snap['available']);
    }

    public function test_uncertain_retains_accounting(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $reservation = $service->reserve(3_000);

        $uncertain = $service->markUncertain($reservation, 'cleanup not confirmed');
        $this->assertSame(StorageReservation::STATE_UNCERTAIN, $uncertain->state);

        $snap = $service->snapshot();
        $this->assertSame(3_000, $snap['reserved_bytes']);
        $this->assertSame(7_000, $snap['available']);
    }

    public function test_release_used_for_file_is_idempotent(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $fileId = (string) Str::uuid();
        $r = $service->reserve(2_000);
        $service->settle($r, $fileId, 2_000);

        $service->releaseUsedForFile($fileId, 2_000);
        $this->assertSame(0, $service->snapshot()['used_bytes']);

        // Retry must not undercount.
        $service->releaseUsedForFile($fileId, 2_000);
        $this->assertSame(0, $service->snapshot()['used_bytes']);

        $reservation = StorageReservation::query()->where('file_id', $fileId)->first();
        $this->assertTrue((bool) $reservation?->usage_released);
    }

    public function test_legacy_release_used_is_disabled(): void
    {
        $service = $this->serviceWithLimit(10_000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('releaseUsedForFile');
        $service->releaseUsed(100);
    }

    public function test_lock_account_bootstraps_when_row_missing(): void
    {
        StorageAccount::query()->delete();
        $this->assertNull(
            StorageAccount::query()->where('scope_key', StorageAccount::SCOPE_TENANT)->first()
        );

        $service = $this->serviceWithLimit(5_000);
        $reservation = $service->reserve(100);

        $this->assertSame(StorageReservation::STATE_PENDING, $reservation->state);
        $account = StorageAccount::query()->where('scope_key', StorageAccount::SCOPE_TENANT)->first();
        $this->assertNotNull($account);
        $this->assertSame(100, (int) $account->reserved_bytes);
    }

    public function test_production_default_limit_is_one_tib(): void
    {
        $service = $this->serviceWithProductionDefault();
        $snap = $service->snapshot();
        $this->assertSame(1_099_511_627_776, $snap['limit']);
    }

    public function test_reservation_rejects_conflicting_bytes_for_same_operation_id(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $op = (string) Str::uuid();
        $service->reserve(500, $op);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicting reservation arguments');
        $service->reserve(600, $op);
    }

    public function test_reservation_idempotent_after_account_lock_path(): void
    {
        // Simulates the post-lock recheck path: first call creates, second with
        // the same operation_id and bytes returns the same row without double-counting.
        $service = $this->serviceWithLimit(10_000);
        $op = (string) Str::uuid();

        $a = $service->reserve(250, $op);
        $b = $service->reserve(250, $op);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(250, $service->snapshot()['reserved_bytes']);
        $this->assertSame(1, StorageReservation::query()->where('operation_id', $op)->count());
    }

    public function test_fallback_release_used_for_file_is_idempotent_without_settled_reservation(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $fileId = (string) Str::uuid();

        // Simulate usage that exists on the ledger without a prior reservation link
        // (e.g. backfilled pre-existing file).
        StorageAccount::query()->where('scope_key', StorageAccount::SCOPE_TENANT)->update([
            'used_bytes' => 1_500,
        ]);

        $service->releaseUsedForFile($fileId, 1_500);
        $this->assertSame(0, $service->snapshot()['used_bytes']);

        // Concurrent-style retry: second call must not subtract again.
        $service->releaseUsedForFile($fileId, 1_500);
        $this->assertSame(0, $service->snapshot()['used_bytes']);

        $sentinel = StorageReservation::query()
            ->where('operation_id', 'usage-release:'.$fileId)
            ->first();
        $this->assertNotNull($sentinel);
        $this->assertTrue((bool) $sentinel->usage_released);
        $this->assertSame(1, StorageReservation::query()->where('file_id', $fileId)->count());
    }

    public function test_account_bootstrap_via_insert_or_ignore_when_row_missing(): void
    {
        StorageAccount::query()->delete();
        $service = $this->serviceWithLimit(5_000);

        // Two sequential bootstraps in the same process: second must not fail or
        // create a second account (unique scope_key + insertOrIgnore path).
        $service->ensureAccount();
        $service->ensureAccount();

        $this->assertSame(
            1,
            StorageAccount::query()->where('scope_key', StorageAccount::SCOPE_TENANT)->count()
        );

        $service->reserve(50);
        $this->assertSame(50, $service->snapshot()['reserved_bytes']);
    }

    public function test_consistent_lock_order_reserve_then_settle_same_reservation(): void
    {
        // Documents account→reservation lock order: overlapping reserve retry
        // and settle on the same operation must both succeed without deadlock
        // under sequential execution (server multi-connection stress is follow-up).
        $service = $this->serviceWithLimit(10_000);
        $op = (string) Str::uuid();
        $fileId = (string) Str::uuid();

        $pending = $service->reserve(800, $op);
        $retry = $service->reserve(800, $op);
        $this->assertSame($pending->id, $retry->id);

        $settled = $service->settle($pending, $fileId, 750);
        $this->assertSame(StorageReservation::STATE_SETTLED, $settled->state);
        $this->assertSame(750, $service->snapshot()['used_bytes']);
        $this->assertSame(0, $service->snapshot()['reserved_bytes']);

        // Settle retry (idempotent) after account→reservation order.
        $again = $service->settle($pending, $fileId, 750);
        $this->assertSame($settled->id, $again->id);

        $service->releaseUsedForFile($fileId, 750);
        $this->assertSame(0, $service->snapshot()['used_bytes']);
    }

    public function test_release_pending_uses_account_then_reservation_lock_order(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $pending = $service->reserve(300);
        $released = $service->release($pending, 'abort');
        $this->assertSame(StorageReservation::STATE_RELEASED, $released->state);
        $this->assertSame(0, $service->snapshot()['reserved_bytes']);
    }

    public function test_migration_backfill_sums_existing_fm_files_only(): void
    {
        // Focused schema: pre-create fm_files with quota-covered sizes, then run
        // the same backfill logic the production migration uses.
        Schema::dropIfExists('fm_files');
        Schema::create('fm_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('size_bytes')->default(0);
        });

        DB::table('fm_files')->insert([
            ['id' => (string) Str::uuid(), 'size_bytes' => 100],
            ['id' => (string) Str::uuid(), 'size_bytes' => 250],
            ['id' => (string) Str::uuid(), 'size_bytes' => 50],
        ]);

        StorageAccount::query()->delete();

        $usedBytes = (int) DB::table('fm_files')->sum('size_bytes');
        $this->assertSame(400, $usedBytes);

        DB::table('fm_storage_accounts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'scope_key' => StorageAccount::SCOPE_TENANT,
            'used_bytes' => $usedBytes,
            'reserved_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($usedBytes > 0) {
            DB::table('fm_storage_accounts')
                ->where('scope_key', StorageAccount::SCOPE_TENANT)
                ->where('used_bytes', '<', $usedBytes)
                ->update(['used_bytes' => $usedBytes, 'updated_at' => now()]);
        }

        $service = $this->serviceWithLimit(10_000);
        $this->assertSame(400, $service->snapshot()['used_bytes']);
        $this->assertSame(9_600, $service->snapshot()['available']);

        // Spatie-style objects are not in fm_files — they never affect the ledger.
        // (No media table involved; the backfill only reads fm_files.)
    }

    private function migrateQuotaSchema(): void
    {
        Schema::dropIfExists('fm_storage_reservations');
        Schema::dropIfExists('fm_storage_accounts');
        Schema::dropIfExists('fm_files');

        Schema::create('fm_storage_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_key', 64)->unique();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedBigInteger('reserved_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('fm_storage_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_key', 64);
            $table->string('operation_id', 64)->unique();
            $table->unsignedBigInteger('reserved_bytes');
            $table->unsignedBigInteger('settled_bytes')->nullable();
            $table->string('state', 32);
            $table->uuid('file_id')->nullable();
            $table->boolean('usage_released')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        StorageAccount::query()->create([
            'scope_key' => StorageAccount::SCOPE_TENANT,
            'used_bytes' => 0,
            'reserved_bytes' => 0,
        ]);
    }

    private function serviceWithLimit(int $limit): StorageQuotaService
    {
        $registry = new EntitlementRegistry();
        FileManagerFeatures::register($registry);
        $registry->seal();

        $resolver = new EntitlementResolver(
            $registry,
            [],
            new DefaultEntitlementProfile([
                FileManagerFeatures::STORAGE_BYTES_KEY => $limit,
            ])
        );

        return new StorageQuotaService(new CapacityEvaluator($resolver));
    }

    private function serviceWithProductionDefault(): StorageQuotaService
    {
        $registry = new EntitlementRegistry();
        FileManagerFeatures::register($registry);
        $registry->seal();

        $resolver = new EntitlementResolver(
            $registry,
            [],
            new DefaultEntitlementProfile()
        );

        return new StorageQuotaService(new CapacityEvaluator($resolver));
    }
}
