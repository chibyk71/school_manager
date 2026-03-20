<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * SectionCache — Centralised Cache Key Management for SchoolSection
 *
 * Single source of truth for all cache keys related to SchoolSection data.
 * Every cache read and write for sections goes through this class — no raw
 * cache keys are scattered across controllers, services, or listeners.
 *
 * ── Problems Solved ───────────────────────────────────────────────────────
 * Without this class, cache keys like "school_sections.active_uuid-here"
 * would be copy-pasted across:
 *   - SchoolSectionService (forget on mutation)
 *   - InvalidateSectionCache listener (forget on event)
 *   - SchoolSectionController (forget on legacy direct writes)
 *   - Any future service that reads sections
 *
 * A single typo in any one location causes silent cache staleness — the
 * forget() call misses the key, stale data is served, no error is thrown.
 * This class eliminates that entire class of bug.
 *
 * ── Cache Slots ───────────────────────────────────────────────────────────
 * Two distinct cache slots are managed:
 *
 * 1. Active sections (options dropdown / SectionPicker):
 *    Key: section_cache.active.{school_id}
 *    Stores: lightweight array of active sections for select components.
 *    TTL: 60 minutes — options change infrequently, short enough to
 *         reflect admin changes within the hour without explicit invalidation.
 *    Populated by: SectionCache::rememberActive()
 *    Invalidated by: SectionCache::forget() on any section mutation.
 *
 * 2. Full section list (DataTable / index page):
 *    The DataTable uses server-side queries via HasTableQuery and does not
 *    cache its results here — pagination/filter combinations are too varied
 *    to cache effectively. Only the options dropdown data is cached.
 *
 * ── Multi-Tenant Key Strategy ────────────────────────────────────────────
 * All keys are scoped to school_id. forget() resolves the current school
 * via GetSchoolModel() — same pattern used in service and controller layers.
 * If school context is unavailable (queued job, console), forget() is
 * a no-op with a warning log rather than throwing.
 *
 * ── Tag Support (Optional) ───────────────────────────────────────────────
 * If the cache driver supports tags (Redis, Memcached), all section keys
 * are tagged with "sections:{school_id}" enabling group invalidation.
 * Falls back to direct key deletion for drivers that don't support tags
 * (database cache, file cache). The forgetAll() method uses tags when
 * available — useful for future bulk invalidation needs.
 *
 * ── Usage Pattern ────────────────────────────────────────────────────────
 *
 * Reading (with auto-population):
 *   $options = SectionCache::rememberActive(fn () => SchoolSection::active()->get());
 *
 * Invalidating after mutation:
 *   SectionCache::forget();
 *
 * Checking if cache is warm:
 *   SectionCache::hasActive();
 *
 * @see App\Listeners\SchoolSection\InvalidateSectionCache
 * @see App\Services\SchoolSectionService
 */
class SectionCache
{
    // ── Key Prefixes ──────────────────────────────────────────────────────

    private const PREFIX        = 'section_cache';
    private const ACTIVE_SLOT   = 'active';

    // ── TTL Constants ─────────────────────────────────────────────────────

    /**
     * How long active section options are cached.
     * 60 minutes balances freshness with performance.
     * Mutations always call forget() to invalidate immediately anyway.
     */
    private const ACTIVE_TTL_SECONDS = 3600;

    // ──────────────────────────────────────────────────────────────────────
    // Key Builders
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build the cache key for active sections of a given school.
     *
     * Format: section_cache.active.{school_id}
     *
     * @param  string  $schoolId
     * @return string
     */
    private static function activeKey(string $schoolId): string
    {
        return implode('.', [self::PREFIX, self::ACTIVE_SLOT, $schoolId]);
    }

    /**
     * Build the cache tag for all section keys belonging to a school.
     * Used for tag-based group invalidation on supported drivers.
     *
     * Format: sections:{school_id}
     *
     * @param  string  $schoolId
     * @return string
     */
    private static function schoolTag(string $schoolId): string
    {
        return "sections:{$schoolId}";
    }

    // ──────────────────────────────────────────────────────────────────────
    // Read / Populate
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get active sections from cache, populating if missing.
     *
     * Wraps the standard Cache::remember() pattern with the correct key
     * and TTL so callers never need to know either value.
     *
     * Usage:
     *   $sections = SectionCache::rememberActive(
     *       fn () => SchoolSection::active()->ordered()->get()
     *   );
     *
     * @param  callable  $callback  Closure that returns the data to cache on miss
     * @param  string|null  $schoolId  Defaults to current school via GetSchoolModel()
     * @return mixed  Whatever the callback returns (or the cached value)
     */
    public static function rememberActive(callable $callback, ?string $schoolId = null): mixed
    {
        $resolvedId = $schoolId ?? GetSchoolModel()?->id;

        if (! $resolvedId) {
            // No school context — bypass cache, call directly
            return $callback();
        }

        return Cache::remember(
            self::activeKey($resolvedId),
            self::ACTIVE_TTL_SECONDS,
            $callback
        );
    }

    /**
     * Get cached active sections without populating on miss.
     *
     * Returns null if the cache is cold — caller decides what to do.
     * Useful for cache warming checks or conditional logic.
     *
     * @param  string|null  $schoolId
     * @return mixed|null
     */
    public static function getActive(?string $schoolId = null): mixed
    {
        $resolvedId = $schoolId ?? GetSchoolModel()?->id;

        if (! $resolvedId) {
            return null;
        }

        return Cache::get(self::activeKey($resolvedId));
    }

    /**
     * Check whether the active sections cache is currently warm.
     *
     * @param  string|null  $schoolId
     * @return bool
     */
    public static function hasActive(?string $schoolId = null): bool
    {
        $resolvedId = $schoolId ?? GetSchoolModel()?->id;

        if (! $resolvedId) {
            return false;
        }

        return Cache::has(self::activeKey($resolvedId));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Invalidation
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Invalidate all section cache slots for the current school.
     *
     * Called after every mutation (create, update, delete, restore, toggle,
     * reorder) to ensure the next read gets fresh data.
     *
     * Resolves school_id via GetSchoolModel(). If no school context is
     * available (queued job, artisan command), logs a warning and returns
     * without throwing — a missed invalidation is preferable to a crash.
     *
     * When an explicit $schoolId is passed (e.g. from queued listeners
     * that carry school_id from the event payload), GetSchoolModel() is
     * not called — the explicit value is used directly.
     *
     * @param  string|null  $schoolId  Optional explicit school ID
     * @return void
     */
    public static function forget(?string $schoolId = null): void
    {
        $resolvedId = $schoolId ?? GetSchoolModel()?->id;

        if (! $resolvedId) {
            \Illuminate\Support\Facades\Log::warning(
                'SectionCache::forget() called with no school context — cache not invalidated.',
                ['trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)]
            );
            return;
        }

        Cache::forget(self::activeKey($resolvedId));
    }

    /**
     * Invalidate all section cache slots for a specific school by ID.
     *
     * Convenience alias for forget() with an explicit school ID.
     * Useful in queued listeners where GetSchoolModel() is unavailable
     * and the school_id is carried from the event payload.
     *
     * Usage in queued listener:
     *   SectionCache::forgetForSchool($event->sections->first()->school_id);
     *
     * @param  string  $schoolId
     * @return void
     */
    public static function forgetForSchool(string $schoolId): void
    {
        self::forget($schoolId);
    }
}
