/**
 * useDynamicEnums — load selectable options by explicit Dynamic Enum definition key.
 *
 * Phase 5/7: options come from GET /dynamic-enums/{key}/options (resolver-backed).
 * Consumers operate on canonical scalar values, not option UUIDs.
 * This is not an administration abstraction.
 */

import { ref, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import type {
    DynamicEnumEffectiveOption,
    DynamicEnumOptionsResponse,
} from '@/types/dynamic-enums';

const memoryCache = new Map<string, DynamicEnumEffectiveOption[]>();

function generateCacheKey(key: string, schoolId: string | number | null): string {
    return `dynamic-enum:${schoolId ?? 'global'}:${key}`;
}

export function useDynamicEnums() {
    const options = ref<DynamicEnumEffectiveOption[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const page = usePage<PageProps>();
    const schoolId = computed(() => (page.props as any).school?.id ?? null);

    const load = async (key: string): Promise<DynamicEnumEffectiveOption[]> => {
        const cacheKey = generateCacheKey(key, schoolId.value);

        if (memoryCache.has(cacheKey)) {
            options.value = memoryCache.get(cacheKey)!;
            return options.value;
        }

        loading.value = true;
        error.value = null;

        try {
            const url = route('dynamic-enums.options', key);
            const response = await axios.get<DynamicEnumOptionsResponse>(url);
            options.value = response.data.options ?? [];
            memoryCache.set(cacheKey, options.value);
            return options.value;
        } catch (err: any) {
            error.value = err?.response?.data?.message ?? 'Failed to load options';
            console.error('[useDynamicEnums] Failed to load options:', err);
            options.value = [];
            return options.value;
        } finally {
            loading.value = false;
        }
    };

    const clearCache = () => {
        memoryCache.clear();
    };

    return { options, loading, error, load, clearCache };
}
