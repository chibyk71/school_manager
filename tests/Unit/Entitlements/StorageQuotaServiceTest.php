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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
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

        // Idempotent settle
        $again = $service->settle($reservation, $fileId, 2_500);
        $this->assertSame(StorageReservation::STATE_SETTLED, $again->state);
        $this->assertSame(2_500, $service->snapshot()['used_bytes']);
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

    public function test_release_used_after_confirmed_purge(): void
    {
        $service = $this->serviceWithLimit(10_000);
        $r = $service->reserve(2_000);
        $service->settle($r, (string) Str::uuid(), 2_000);

        $service->releaseUsed(2_000);

        $this->assertSame(0, $service->snapshot()['used_bytes']);
        $this->assertSame(10_000, $service->snapshot()['available']);
    }

    public function test_production_default_limit_is_one_tib(): void
    {
        $service = $this->serviceWithProductionDefault();
        $snap = $service->snapshot();
        $this->assertSame(1_099_511_627_776, $snap['limit']);
    }

    private function migrateQuotaSchema(): void
    {
        Schema::dropIfExists('fm_storage_reservations');
        Schema::dropIfExists('fm_storage_accounts');

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
