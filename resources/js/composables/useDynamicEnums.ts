// resources/js/Modules/DynamicEnums/Composables/useDynamicEnums.ts
/**
 * useDynamicEnums.ts
 *
 * Vue 3 composable for fetching dynamic enum options from the backend API.
 *
 * Features / Problems Solved:
 * - Provides a simple, reactive way to load allowed options for any dynamic enum property
 *   (e.g., gender, title, profile_type) directly from the dedicated API endpoint
 *   (/api/dynamic-enums/options/{appliesTo}/{name}).
 * - Fully tenant-aware: automatically includes the current school context via Inertia page props
 *   (page.props.auth.school.id) – ensures school overrides are returned, falling back to global defaults.
 * - Reactive state: options (array), loading (boolean), error (string|null).
 * - Built-in caching strategy:
 *     • In-memory cache (Map) for the lifetime of the SPA session (fast repeats).
 *     • Optional persistent cache via localStorage (per-school key) for better perceived performance
 *       on page reloads/navigation (disabled by default – enable if needed).
 * - Error handling: user-friendly message on failure, console logging for devs.
 * - Type-safe: returns properly typed options [{ value: string, label: string, color?: string }].
 * - Lightweight and on-demand: only fetches when load() is called (perfect for forms/mount hooks).
 * - Integrates seamlessly with existing patterns:
 *     • Can be used directly in form components (DynamicSelect, DynamicRadio).
 *     • Compatible with useAsyncOptions.ts if you want debounced search (this one is exact match).
 *     • Uses Axios instance (configured with CSRF, auth headers).
 * - Performance: single request per unique property/model combination.
 *
 * Fits into the DynamicEnums Module:
 * - Bridges the backend API (DynamicEnumController@options) to frontend form fields.
 * - First frontend piece – powers DynamicSelect.vue, DynamicRadio.vue, and any custom form fields.
 * - Enables truly dynamic, school-customizable dropdowns/radios without hardcoding options.
 * - Works with Inertia.js page props for school context (no extra requests needed).
 * - Production-ready: responsive to school changes (clears cache on school switch if implemented).
 */

import { ref, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface DynamicEnumOption {
    value: string;
    label: string;
    color?: string;
}

interface CachedEntry {
    options: DynamicEnumOption[];
    timestamp: number;
}

// In-memory cache (cleared on page refresh)
const memoryCache = new Map<string, DynamicEnumOption[]>();

// Optional persistent cache – uncomment localStorage lines if you want persistence across reloads
// const PERSISTENT_CACHE = true;
// const CACHE_TTL = 1000 * 60 * 30; // 30 minutes

function generateCacheKey(appliesTo: string, name: string, schoolId: string | number | null): string {
    return `dynamic-enum:${schoolId ?? 'global'}:${appliesTo}:${name}`;
}

export function useDynamicEnums() {
    const options = ref<DynamicEnumOption[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const page = usePage<PageProps>();
    const schoolId = computed(() => page.props.school?.id ?? null);

    /**
     * Load options for a specific dynamic enum property.
     *
     * @param appliesTo Fully qualified model class (e.g., 'App\\Models\\Profile')
     * @param name      Property/machine name (e.g., 'gender')
     */
    const load = async (appliesTo: string, name: string): Promise<DynamicEnumOption[]> => {
        const cacheKey = generateCacheKey(appliesTo, name, schoolId.value);

        // Return from memory cache if available
        if (memoryCache.has(cacheKey)) {
            options.value = memoryCache.get(cacheKey)!;
            return options.value;
        }

        // Optional: Check persistent cache
        // if (PERSISTENT_CACHE) {
        //     const cached = localStorage.getItem(cacheKey);
        //     if (cached) {
        //         const entry: CachedEntry = JSON.parse(cached);
        //         if (Date.now() - entry.timestamp < CACHE_TTL) {
        //             options.value = entry.options;
        //             memoryCache.set(cacheKey, entry.options);
        //             return options.value;
        //         }
        //     }
        // }

        loading.value = true;
        error.value = null;

        try {
            // URL-encode the namespace slashes
            const encodedAppliesTo = encodeURIComponent(appliesTo);
            const url = `/api/dynamic-enums/options/${encodedAppliesTo}/${name}`;

            const response = await axios.get<{ options: DynamicEnumOption[] }>(url);

            options.value = response.data.options ?? [];

            // Cache in memory
            memoryCache.set(cacheKey, options.value);

            // Optional: Cache persistently
            // if (PERSISTENT_CACHE) {
            //     localStorage.setItem(
            //         cacheKey,
            //         JSON.stringify({ options: options.value, timestamp: Date.now() } as CachedEntry)
            //     );
            // }

            return options.value;
        } catch (err: any) {
            console.error('[useDynamicEnums] Failed to load options:', err);
            error.value =
                err.response?.data?.message ||
                'Failed to load options. Please try again or contact support.';

            options.value = [];
            return [];
        } finally {
            loading.value = false;
        }
    };

    /**
     * Clear all caches – useful when school changes or after admin updates enums.
     */
    const clearCache = () => {
        memoryCache.clear();
        // if (PERSISTENT_CACHE) {
        //     Object.keys(localStorage)
        //         .filter(key => key.startsWith('dynamic-enum:'))
        //         .forEach(key => localStorage.removeItem(key));
        // }
    };

    return {
        /** Reactive array of options [{ value, label, color? }] */
        options: computed(() => options.value),

        /** Reactive loading state */
        loading: computed(() => loading.value),

        /** Reactive error message */
        error: computed(() => error.value),

        /** Load options for a property – call on mount or when needed */
        load,

        /** Manually clear all caches (e.g., after saving enum changes) */
        clearCache,
    };
}
