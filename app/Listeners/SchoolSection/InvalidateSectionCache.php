<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionCreated;
use App\Events\SchoolSection\SchoolSectionDeactivated;
use App\Events\SchoolSection\SchoolSectionDeleted;
use App\Events\SchoolSection\SchoolSectionRestored;
use App\Events\SchoolSection\SchoolSectionUpdated;
use App\Support\SectionCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * InvalidateSectionCache
 *
 * Clears the per-school section cache after any SchoolSection mutation.
 * Registered for all five domain events so no mutation can leave stale
 * cached data behind regardless of which operation triggered the change.
 *
 * ── Why One Listener For Five Events ─────────────────────────────────────
 * All five events require identical cache invalidation logic: call
 * SectionCache::forget(). Separate listeners per event would duplicate
 * a single line of code five times with no benefit.
 * Laravel resolves the correct handle() overload via event type-hinting,
 * so one class can listen to multiple events cleanly.
 *
 * ── ShouldHandleEventsAfterCommit ────────────────────────────────────────
 * This interface ensures the cache is only invalidated after the database
 * transaction commits. Without this, a cache invalidation could fire
 * inside a transaction that later rolls back — leaving the cache empty
 * while the DB still has the old data. With this interface, if the
 * transaction rolls back, this listener never runs.
 *
 * ── Synchronous ──────────────────────────────────────────────────────────
 * Cache invalidation is not queued. The operation is instant (one cache
 * key deletion) and must be synchronous so the next request that reads
 * the cache sees fresh data. Queuing it would introduce a race window
 * where the old cached value is served between the DB write and the
 * background job running.
 *
 * ── SectionCache ─────────────────────────────────────────────────────────
 * SectionCache::forget() encapsulates the cache key format. This listener
 * never knows or cares what the key looks like — that's SectionCache's job.
 *
 * @see App\Support\SectionCache
 * @see App\Events\SchoolSection\*
 */
class InvalidateSectionCache implements ShouldHandleEventsAfterCommit
{
    public function handleSchoolSectionCreated(SchoolSectionCreated $event): void
    {
        SectionCache::forget();
    }

    public function handleSchoolSectionUpdated(SchoolSectionUpdated $event): void
    {
        SectionCache::forget();
    }

    public function handleSchoolSectionDeactivated(SchoolSectionDeactivated $event): void
    {
        SectionCache::forget();
    }

    public function handleSchoolSectionDeleted(SchoolSectionDeleted $event): void
    {
        SectionCache::forget();
    }

    public function handleSchoolSectionRestored(SchoolSectionRestored $event): void
    {
        SectionCache::forget();
    }
}
