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

/**
 * Concurrency-safe storage capacity reservation and settlement for File Manager.
 *
 * Coordination:
 * 1. Lock the tenant StorageAccount row FOR UPDATE (create safely if missing).
 * 2. Evaluate used + reserved against the entitlement limit.
 * 3. Insert a pending reservation and increment reserved_bytes atomically.
 * 4. After physical write + metadata, settle under the same lock rules:
 *    return the reserved hold, re-check capacity for the authoritative size,
 *    then charge used_bytes.
 * 5. On confirmed cleanup, release; on uncertain outcome, mark uncertain and retain.
 * 6. On confirmed permanent file deletion, release used capacity once per file_id.
 *
 * Mutations are idempotent by operation_id (reservations) and by file_id
 * (usage release). Conflicting settlement arguments are rejected.
 *
 * Account bootstrap uses insertOrIgnore so unique conflicts do not abort the
 * surrounding transaction (PostgreSQL-safe). Reservation create re-checks
 * operation_id after the account lock. Usage release claims a unique sentinel
 * before subtracting so concurrent fallbacks cannot double-release.
 */
final class StorageQuotaService
{
    public function __construct(
        private readonly CapacityEvaluator $capacity,
    ) {}

    /**
     * Atomically reserve capacity for an upcoming upload.
     *
     * Matching retries with the same operation_id and reserved_bytes return the
     * existing reservation. Conflicting reserved_bytes for the same operation_id
     * are rejected. Concurrent callers serialize on the account row and re-check
     * operation_id after the lock so only one insert occurs.
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
            // Serialize on the account first so concurrent same-operation_id
            // callers cannot both pass a pre-lock absence check.
            $account = $this->lockAccount();

            $existing = StorageReservation::query()
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $this->resolveExistingReservation($existing, $bytes, $operationId);
            }

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
                'usage_released' => false,
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
     * Transitions reserved → used under the account lock. If the actual size
     * exceeds the original reservation, capacity is re-checked so the limit
     * cannot be exceeded. Idempotent only for the same file_id and actualBytes;
     * conflicting retries are rejected without mutating the ledger.
     *
     * @throws InsufficientCapacityException
     */
    public function settle(StorageReservation $reservation, string $fileId, int $actualBytes): StorageReservation
    {
        if ($actualBytes < 0) {
            throw new InvalidArgumentException('Settled bytes must be non-negative.');
        }

        if ($fileId === '') {
            throw new InvalidArgumentException('Settlement requires a non-empty file_id.');
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
                if ((string) $locked->file_id !== $fileId
                    || (int) $locked->settled_bytes !== $actualBytes) {
                    throw new RuntimeException(
                        'Conflicting settlement arguments for an already-settled reservation. '
                        .'Expected file_id='.(string) $locked->file_id
                        .' settled_bytes='.(int) $locked->settled_bytes
                        ."; got file_id={$fileId} settled_bytes={$actualBytes}."
                    );
                }

                return $locked;
            }

            if ($locked->state === StorageReservation::STATE_RELEASED) {
                throw new RuntimeException('Cannot settle a released reservation.');
            }

            $account = $this->lockAccount();

            $reserved = (int) $locked->reserved_bytes;

            // Return the reserved hold first so available capacity is computed
            // without this reservation still counting as outstanding.
            $account->reserved_bytes = max(0, (int) $account->reserved_bytes - $reserved);

            // Re-check capacity for the authoritative size (may exceed the
            // original reservation). Fail closed under the same account lock.
            $this->capacity->assertWithinLimit(
                FileManagerFeatures::STORAGE_BYTES_KEY,
                $actualBytes,
                (int) $account->used_bytes,
                (int) $account->reserved_bytes,
            );

            $account->used_bytes = (int) $account->used_bytes + $actualBytes;
            $account->save();

            $locked->state = StorageReservation::STATE_SETTLED;
            $locked->settled_bytes = $actualBytes;
            $locked->file_id = $fileId;
            $locked->usage_released = false;
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
                throw new RuntimeException(
                    'Cannot release a settled reservation; use releaseUsedForFile for file deletion.'
                );
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
     *
     * Idempotent per file_id under concurrency:
     * - Settled reservation path: lock the reservation; if already usage_released, no-op;
     *   otherwise mark usage_released and subtract settled_bytes under the account lock.
     * - Fallback (no settled reservation): claim a unique sentinel row via insertOrIgnore
     *   before subtracting. Only the claim winner subtracts; losers no-op without
     *   touching the ledger.
     */
    public function releaseUsedForFile(string $fileId, int $bytes): void
    {
        if ($fileId === '') {
            throw new InvalidArgumentException('file_id is required for usage release.');
        }

        if ($bytes < 0) {
            throw new InvalidArgumentException('Released bytes must be non-negative.');
        }

        if ($bytes === 0) {
            return;
        }

        DB::transaction(function () use ($fileId, $bytes) {
            /** @var StorageReservation|null $reservation */
            $reservation = StorageReservation::query()
                ->where('file_id', $fileId)
                ->where('state', StorageReservation::STATE_SETTLED)
                ->lockForUpdate()
                ->first();

            if ($reservation !== null) {
                if ($reservation->usage_released) {
                    return;
                }

                $releaseBytes = (int) $reservation->settled_bytes;
                if ($releaseBytes <= 0) {
                    $reservation->usage_released = true;
                    $reservation->save();

                    return;
                }

                $account = $this->lockAccount();
                $account->used_bytes = max(0, (int) $account->used_bytes - $releaseBytes);
                $account->save();

                $reservation->usage_released = true;
                $reservation->save();

                return;
            }

            // Fallback: claim the release before any ledger mutation so concurrent
            // callers cannot both subtract. insertOrIgnore is portable across
            // SQLite / MySQL / PostgreSQL and does not abort the transaction.
            $now = now();
            $claimed = DB::table('fm_storage_reservations')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'scope_key' => StorageAccount::SCOPE_TENANT,
                'operation_id' => 'usage-release:'.$fileId,
                'reserved_bytes' => 0,
                'settled_bytes' => $bytes,
                'state' => StorageReservation::STATE_SETTLED,
                'file_id' => $fileId,
                'usage_released' => true,
                'notes' => 'Usage release claim without prior settled reservation.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($claimed === 0) {
                // Another connection already claimed this file_id release.
                return;
            }

            $account = $this->lockAccount();
            $account->used_bytes = max(0, (int) $account->used_bytes - $bytes);
            $account->save();
        });
    }

    /**
     * @deprecated Use releaseUsedForFile() for idempotent per-file release.
     */
    public function releaseUsed(int $bytes): void
    {
        throw new RuntimeException(
            'releaseUsed(int) is not idempotent and must not be used. '
            .'Call releaseUsedForFile(string $fileId, int $bytes) instead.'
        );
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
        return DB::transaction(fn () => $this->lockAccount());
    }

    /**
     * Lock the tenant storage account row for capacity decisions.
     *
     * Missing-row bootstrap uses insertOrIgnore so a unique conflict does not
     * abort the surrounding transaction (required for PostgreSQL). The winner
     * and losers then SELECT ... FOR UPDATE on the same row.
     *
     * @throws RuntimeException
     */
    private function lockAccount(): StorageAccount
    {
        /** @var StorageAccount|null $account */
        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->lockForUpdate()
            ->first();

        if ($account !== null) {
            return $account;
        }

        $now = now();
        DB::table('fm_storage_accounts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'scope_key' => StorageAccount::SCOPE_TENANT,
            'used_bytes' => 0,
            'reserved_bytes' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            throw new RuntimeException('Unable to lock storage account for quota decision.');
        }

        return $account;
    }

    /**
     * @throws RuntimeException
     */
    private function resolveExistingReservation(
        StorageReservation $existing,
        int $requestedBytes,
        string $operationId,
    ): StorageReservation {
        if ($existing->state === StorageReservation::STATE_RELEASED) {
            throw new RuntimeException(
                "Reservation operation [{$operationId}] was already released."
            );
        }

        // Matching retry: same operation_id and same reserved amount.
        if ((int) $existing->reserved_bytes === $requestedBytes) {
            return $existing;
        }

        throw new RuntimeException(
            "Conflicting reservation arguments for operation [{$operationId}]. "
            .'Expected reserved_bytes='.(int) $existing->reserved_bytes
            ."; got reserved_bytes={$requestedBytes}."
        );
    }
}
