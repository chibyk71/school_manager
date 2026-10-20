<?php

namespace App\Services\FileManager;

use App\Entitlements\Definitions\FileManagerFeatures;
use App\Models\FileManager\StorageAccount;
use App\Models\FileManager\StorageReservation;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Concurrency-safe storage capacity reservation and settlement for File Manager.
 *
 * Coordination:
 * 1. Lock the tenant StorageAccount row FOR UPDATE.
 * 2. Evaluate used + reserved against the entitlement limit.
 * 3. Insert a pending reservation and increment reserved_bytes atomically.
 * 4. After physical write + metadata, settle (move reserved → used by actual size).
 * 5. On confirmed cleanup, release; on uncertain outcome, mark uncertain and retain.
 *
 * All mutations are idempotent by operation_id.
 */
final class StorageQuotaService
{
    public function __construct(
        private readonly CapacityEvaluator $capacity,
    ) {}

    /**
     * Atomically reserve capacity for an upcoming upload.
     *
     * @return StorageReservation  Pending reservation (or existing if same operation_id).
     *
     * @throws InsufficientCapacityException
     */
    public function reserve(int $bytes, ?string $operationId = null): StorageReservation
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Reservation bytes must be non-negative.');
        }

        $operationId = $operationId ?: (string) Str::uuid();

        return DB::transaction(function () use ($bytes, $operationId) {
            // Idempotent: return existing outstanding/settled reservation for same operation.
            $existing = StorageReservation::query()
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->state === StorageReservation::STATE_RELEASED) {
                    throw new RuntimeException(
                        "Reservation operation [{$operationId}] was already released."
                    );
                }

                return $existing;
            }

            $account = $this->lockAccount();

            $this->capacity->assertWithinLimit(
                FileManagerFeatures::STORAGE_BYTES_KEY,
                $bytes,
                (int) $account->used_bytes,
                (int) $account->reserved_bytes,
            );

            $reservation = new StorageReservation([
                'scope_key' => StorageAccount::SCOPE_TENANT,
                'operation_id' => $operationId,
                'reserved_bytes' => $bytes,
                'state' => StorageReservation::STATE_PENDING,
            ]);
            $reservation->save();

            $account->reserved_bytes = (int) $account->reserved_bytes + $bytes;
            $account->save();

            return $reservation;
        });
    }

    /**
     * Settle a pending reservation against the authoritative recorded file size.
     *
     * Transitions reserved → used. Idempotent for already-settled reservations
     * with the same file_id and size.
     */
    public function settle(StorageReservation $reservation, string $fileId, int $actualBytes): StorageReservation
    {
        if ($actualBytes < 0) {
            throw new InvalidArgumentException('Settled bytes must be non-negative.');
        }

        return DB::transaction(function () use ($reservation, $fileId, $actualBytes) {
            /** @var StorageReservation|null $locked */
            $locked = StorageReservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new RuntimeException('Reservation no longer exists.');
            }

            if ($locked->state === StorageReservation::STATE_SETTLED) {
                // Idempotent success path.
                return $locked;
            }

            if ($locked->state === StorageReservation::STATE_RELEASED) {
                throw new RuntimeException('Cannot settle a released reservation.');
            }

            $account = $this->lockAccount();

            $reserved = (int) $locked->reserved_bytes;

            // Return the reserved hold, then charge actual used.
            $account->reserved_bytes = max(0, (int) $account->reserved_bytes - $reserved);
            $account->used_bytes = (int) $account->used_bytes + $actualBytes;
            $account->save();

            $locked->state = StorageReservation::STATE_SETTLED;
            $locked->settled_bytes = $actualBytes;
            $locked->file_id = $fileId;
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Release a reservation after confirmed cleanup (no physical object remains).
     * Idempotent.
     */
    public function release(StorageReservation $reservation, ?string $notes = null): StorageReservation
    {
        return DB::transaction(function () use ($reservation, $notes) {
            /** @var StorageReservation|null $locked */
            $locked = StorageReservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new RuntimeException('Reservation no longer exists.');
            }

            if ($locked->state === StorageReservation::STATE_RELEASED) {
                return $locked;
            }

            if ($locked->state === StorageReservation::STATE_SETTLED) {
                throw new RuntimeException('Cannot release a settled reservation; use releaseUsed for file deletion.');
            }

            $account = $this->lockAccount();
            $reserved = (int) $locked->reserved_bytes;
            $account->reserved_bytes = max(0, (int) $account->reserved_bytes - $reserved);
            $account->save();

            $locked->state = StorageReservation::STATE_RELEASED;
            if ($notes !== null) {
                $locked->notes = $notes;
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Mark reservation uncertain — retain accounting until reconciliation confirms outcome.
     * Idempotent for already-uncertain rows.
     */
    public function markUncertain(StorageReservation $reservation, ?string $notes = null): StorageReservation
    {
        return DB::transaction(function () use ($reservation, $notes) {
            /** @var StorageReservation|null $locked */
            $locked = StorageReservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new RuntimeException('Reservation no longer exists.');
            }

            if (in_array($locked->state, [
                StorageReservation::STATE_SETTLED,
                StorageReservation::STATE_RELEASED,
            ], true)) {
                return $locked;
            }

            $locked->state = StorageReservation::STATE_UNCERTAIN;
            if ($notes !== null) {
                $locked->notes = $notes;
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Release used capacity after confirmed permanent deletion of a file.
     * Called from FileLifecycleService after metadata removal.
     */
    public function releaseUsed(int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        DB::transaction(function () use ($bytes) {
            $account = $this->lockAccount();
            $account->used_bytes = max(0, (int) $account->used_bytes - $bytes);
            $account->save();
        });
    }

    /**
     * Current accounted usage (used + outstanding reserved).
     *
     * @return array{used_bytes: int, reserved_bytes: int, limit: int, available: int}
     */
    public function snapshot(): array
    {
        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->first();

        $used = (int) ($account?->used_bytes ?? 0);
        $reserved = (int) ($account?->reserved_bytes ?? 0);
        $limit = $this->capacity->limit(FileManagerFeatures::STORAGE_BYTES_KEY);

        return [
            'used_bytes' => $used,
            'reserved_bytes' => $reserved,
            'limit' => $limit,
            'available' => max(0, $limit - $used - $reserved),
        ];
    }

    /**
     * Ensure the tenant account row exists (for focused test schemas).
     */
    public function ensureAccount(): StorageAccount
    {
        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->first();

        if ($account !== null) {
            return $account;
        }

        $account = new StorageAccount([
            'scope_key' => StorageAccount::SCOPE_TENANT,
            'used_bytes' => 0,
            'reserved_bytes' => 0,
        ]);
        $account->save();

        return $account;
    }

    /**
     * @throws RuntimeException
     */
    private function lockAccount(): StorageAccount
    {
        /** @var StorageAccount|null $account */
        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            // Bootstrap under lock contention path (rare; migration seeds the row).
            $account = new StorageAccount([
                'scope_key' => StorageAccount::SCOPE_TENANT,
                'used_bytes' => 0,
                'reserved_bytes' => 0,
            ]);
            $account->save();

            $account = StorageAccount::query()
                ->where('scope_key', StorageAccount::SCOPE_TENANT)
                ->lockForUpdate()
                ->first();
        }

        if ($account === null) {
            throw new RuntimeException('Unable to lock storage account for quota decision.');
        }

        return $account;
    }
}
