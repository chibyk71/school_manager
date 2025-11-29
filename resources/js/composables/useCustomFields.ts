// ===================================================================
// resources/js/composables/useCustomFields.ts
// Central composable for loading database-driven custom fields
// Features:
//   • Inertia-aware (reads page props first)
//   • Axios fallback
//   • Automatic grouping by category
//   • Smart caching (per model + entity_id + school context)
//   • Reactive loading/error states
//   • Prefills values on edit forms
// ===================================================================

import { ref, computed, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import type {
    CustomField,
    CustomFieldsResponse,
    FieldCategory,
} from '@/types/form';
import { route } from 'ziggy-js';

// -------------------------------------------------------------------
// Cache key generator – ensures isolation per school, model, entity
// -------------------------------------------------------------------
function generateCacheKey(params: {
    model: string;
    entityId?: number | string;
    schoolId?: number | string;
}): string {
    return `custom-fields:${params.schoolId ?? 'global'}:${params.model}:${params.entityId ?? 'create'}`;
}

// -------------------------------------------------------------------
// In-memory cache (simple but effective for SPA lifetime)
// -------------------------------------------------------------------
const cache = new Map<string, { data: FieldCategory[]; values: Record<string, any>; timestamp: number }>();
const CACHE_TTL = 1000 * 60 * 5; // 5 minutes

// -------------------------------------------------------------------
// Main composable
// -------------------------------------------------------------------
export function useCustomFields(options: {
    /** Model name (e.g. "Student", "Teacher") – required */
    model: string;

    /** Entity ID for edit forms (optional) */
    entityId?: number | string;

    /** Force refetch even if cached */
    immediate?: boolean;

    /** Custom fetch URL (overrides default route) */
    fetchUrl?: string;

    /** Additional query params (e.g. school_id from schoolManager) */
    extraParams?: Record<string, any>;
}) {
    const { model, entityId, immediate = true, fetchUrl, extraParams = {} } = options;

    const loading = ref(false);
    const error = ref<string | null>(null);
    const categories = ref<FieldCategory[]>([]);
    const flatFields = computed<CustomField[]>(() =>
        categories.value.flatMap((cat) => cat.fields).sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
    );

    // Prefilled values (for edit forms)
    const initialValues = ref<Record<string, any>>({});

    // Try to get from Inertia page props first (server-side preloaded)
    const page = usePage();
    const pagePropsKey = `customFields_${model}_${entityId ?? 'create'}`;
    if (page.props[pagePropsKey]) {
        const payload = page.props[pagePropsKey] as CustomFieldsResponse;
        categories.value = payload.categories ?? groupIntoCategories(payload.fields ?? []);
        initialValues.value = payload.values ?? {};
        // Mark as loaded if we got it from props
        loading.value = false;
    }

    // -----------------------------------------------------------------
    // Core fetch function
    // -----------------------------------------------------------------
    const fetchFields = async () => {
        const cacheKey = generateCacheKey({
            model,
            entityId,
            schoolId: extraParams.school_id ?? page.props.school?.id,
        });

        // Return cached if fresh
        const cached = cache.get(cacheKey);
        if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
            categories.value = cached.data;
            initialValues.value = cached.values;
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const url =
                fetchUrl ??
                route('api.custom-fields.index', {
                    model,
                    entity_id: entityId,
                    ...extraParams,
                });

            const response = await axios.get<CustomFieldsResponse>(url, {
                headers: { Accept: 'application/json' },
                withCredentials: true,
            });

            const payload = response.data;

            // Normalize: always work with categories
            const grouped = payload.categories ?? groupIntoCategories(payload.fields ?? []);

            // Sort categories alphabetically (or by custom order later)
            categories.value = grouped.sort((a, b) => a.name.localeCompare(b.name));

            initialValues.value = payload.values ?? {};

            // Cache result
            cache.set(cacheKey, {
                data: categories.value,
                values: initialValues.value,
                timestamp: Date.now(),
            });
        } catch (err: any) {
            console.error('[useCustomFields] Fetch failed:', err);
            error.value = err.response?.data?.message ?? 'Failed to load form fields';
            categories.value = [];
            initialValues.value = {};
        } finally {
            loading.value = false;
        }
    };

    // Auto-fetch on mount if not preloaded
    if (immediate && categories.value.length === 0) {
        fetchFields();
    }

    // -----------------------------------------------------------------
    // Helper: convert flat fields → grouped categories
    // -----------------------------------------------------------------
    function groupIntoCategories(fields: CustomField[]): FieldCategory[] {
        const map = new Map<string, CustomField[]>();

        fields.forEach((field) => {
            const catName = field.category ?? 'General';
            if (!map.has(catName)) map.set(catName, []);
            map.get(catName)!.push(field);
        });

        return Array.from(map.entries())
            .map(([name, fields]) => ({
                name,
                label: formatCategoryLabel(name),
                fields: fields.sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0)),
                collapsed: false,
            }))
            .sort((a, b) => a.label.localeCompare(b.label));
    }

    function formatCategoryLabel(name: string): string {
        return name
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (l) => l.toUpperCase());
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------
    return {
        /** Grouped & sorted categories ready for rendering */
        categories: computed(() => categories.value),

        /** Flat list (useful for validation, lookups) */
        fields: flatFields,

        /** Prefilled values (e.g. for edit forms) */
        initialValues: computed(() => initialValues.value),

        /** Loading state */
        loading: computed(() => loading.value),

        /** Error message */
        error: computed(() => error.value),

        /** Manual refetch */
        refetch: fetchFields,

        /** Clear cache (e.g. after field config changes) */
        clearCache: () => {
            const prefix = generateCacheKey({ model, entityId });
            for (const key of cache.keys()) {
                if (key.startsWith(prefix.split(':').slice(0, 3).join(':'))) {
                    cache.delete(key);
                }
            }
        },
    };
}
