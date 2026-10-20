<?php

namespace App\Services\FileManager;

use App\Entitlements\Definitions\FileManagerFeatures;
use App\Models\FileManager\StorageAccount;
use App\Models\FileManager\StorageReservation;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Database\QueryException;
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
     * Idempotent per file_id: a settled reservation linked to the file is marked
     * usage_released once. Retries do not subtract again. Bytes are taken from
     * the settled reservation's settled_bytes when available so callers cannot
     * under- or over-release by repeating a mismatched amount.
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

            if ($reservation !== null && $reservation->usage_released) {
                // Already released for this file — no ledger change.
                return;
            }

            $releaseBytes = $reservation !== null
                ? (int) $reservation->settled_bytes
                : $bytes;

            if ($releaseBytes <= 0) {
                if ($reservation !== null) {
                    $reservation->usage_released = true;
                    $reservation->save();
                }

                return;
            }

            $account = $this->lockAccount();
            $account->used_bytes = max(0, (int) $account->used_bytes - $releaseBytes);
            $account->save();

            if ($reservation !== null) {
                $reservation->usage_released = true;
                $reservation->save();
            } else {
                // No settled reservation row (should not happen for Phase 1 FM uploads).
                // Record a sentinel settled+released row so a retry is still idempotent.
                $sentinel = new StorageReservation([
                    'scope_key' => StorageAccount::SCOPE_TENANT,
                    'operation_id' => 'usage-release:'.$fileId,
                    'reserved_bytes' => 0,
                    'settled_bytes' => $releaseBytes,
                    'state' => StorageReservation::STATE_SETTLED,
                    'file_id' => $fileId,
                    'usage_released' => true,
                    'notes' => 'Usage release without prior reservation link.',
                ]);
                try {
                    $sentinel->save();
                } catch (QueryException $e) {
                    // Concurrent release won the unique operation_id or file path — treat as done.
                    if (! $this->isUniqueViolation($e)) {
                        throw $e;
                    }
                }
            }
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
     * Uses the same concurrency-safe path as lockAccount without requiring an open transaction.
     */
    public function ensureAccount(): StorageAccount
    {
        return DB::transaction(fn () => $this->lockAccount());
    }

    /**
     * Lock the tenant storage account row for capacity decisions.
     *
     * Safe when the row is missing: unique scope_key + catch concurrent insert,
     * then re-select with FOR UPDATE so all callers serialize on the same row.
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

        try {
            $created = new StorageAccount([
                'scope_key' => StorageAccount::SCOPE_TENANT,
                'used_bytes' => 0,
                'reserved_bytes' => 0,
            ]);
            $created->save();
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
            // Concurrent initializer won the unique scope_key insert.
        }

        $account = StorageAccount::query()
            ->where('scope_key', StorageAccount::SCOPE_TENANT)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            throw new RuntimeException('Unable to lock storage account for quota decision.');
        }

        return $account;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 (integrity constraint) covers SQLite/MySQL unique violations.
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = strtolower($e->getMessage());

        return $sqlState === '23000'
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
