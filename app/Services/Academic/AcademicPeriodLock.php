<?php

namespace App\Services\Academic;

use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Shared school-level serialization for Academic period mutations and dependency registration.
 *
 * Protocol (must use the same lock order everywhere):
 *
 *   Academic Session/Term mutation:
 *     BEGIN
 *       lockSchool(schoolId)          // schools row FOR UPDATE
 *       lock academic period rows
 *       inspect registry
 *       mutate
 *     COMMIT
 *
 *   Tracked resource dependency mutation (create/update academic FKs):
 *     BEGIN  (owning service transaction)
 *       lockSchool(schoolId)          // same schools row FOR UPDATE
 *       mutate resource
 *       registry register/unregister  // also re-locks school (no-op if already held)
 *     COMMIT
 *
 * Holding the schools row lock serializes academic date/delete checks with
 * concurrent dependency registration so a dependency cannot appear after the
 * registry was observed empty under the academic lock.
 *
 * Call only inside an open DB transaction. Outside a transaction, FOR UPDATE
 * does not hold across statements (MySQL/SQLite).
 */
final class AcademicPeriodLock
{
    /**
     * Acquire the school-level serialization lock used by Session/Term lifecycle
     * and by academic-usage registration.
     */
    public static function lockSchool(string $schoolId): void
    {
        School::query()
            ->whereKey($schoolId)
            ->lockForUpdate()
            ->first(['id']);
    }

    /**
     * True when the connection is already inside a transaction (including savepoints).
     */
    public static function inTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }
}
