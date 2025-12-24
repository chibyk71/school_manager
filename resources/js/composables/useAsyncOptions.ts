// resources/js/composables/useAsyncOptions.ts
/**
 * useAsyncOptions.ts v3.0 – Production-Ready with Total Support
 *
 * Enhanced for hybrid loading strategy in school management SaaS:
 * - Fully compatible with updated SearchController v2.0 (returns 'total')
 * - Exposes `total` reactive value for AsyncSelect.vue to decide:
 *   • Small dataset (<300): load all pages automatically
 *   • Large dataset: use placeholder array + lazy slice loading (future)
 * - Robust Laravel paginator handling (next_page_url OR current_page/last_page)
 * - Debounced search with proper cleanup
 * - Params reactivity (refreshes on label_field/search_fields change)
 * - Clean error handling and readonly computed exports
 *
 * Perfect for Nigerian schools: most have <500 students/staff → fast full load
 *
 * @param config.url API endpoint (e.g., '/api/search/staff')
 * @param config.params Base/dynamic params (e.g., label_field, search_fields)
 * @param config.searchKey Query param for search (default: 'search')
 * @param config.delay Debounce delay in ms (default: 400)
 *
 * @returns Reactive state and actions for AsyncSelect.vue
 */
import { ref, computed, watch } from 'vue';
import axios from 'axios';

export function useAsyncOptions(config: {
    url: string;
    params?: Record<string, any>;
    searchKey?: string;
    delay?: number;
}) {
    // Reactive state
    const options = ref<any[]>([]);            // Loaded {value, label} items
    const total = ref<number>(0);              // Total records from API (for strategy decisions)
    const loading = ref(false);                // Current request state
    const error = ref<string | null>(null);    // User-friendly error message
    const page = ref(1);                       // Current page
    const searchTerm = ref('');                // Current debounced search term
    const hasMore = ref(true);                 // More pages available?

    // Debounce timer
    let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

    /**
     * Fetch data from the API
     *
     * @param term Search term
     * @param append Append to existing options (infinite scroll)
     */
    const fetch = async (term: string = '', append: boolean = false) => {
        // Reset on new search
        if (!append) {
            page.value = 1;
            options.value = [];
            hasMore.value = true;
        }

        // Prevent overlapping requests during append
        if (loading.value && append) return;

        loading.value = true;
        error.value = null;

        try {
            const params: Record<string, any> = {
                page: page.value,
                per_page: 50, // Matches increased backend limit
                ...(term ? { [config.searchKey ?? 'search']: term } : {}),
                ...(config.params || {}),
            };

            const response = await axios.get(config.url, { params });

            // Handle both Laravel paginator formats
            const newOptions = response.data.data ?? response.data ?? [];

            // Update total records (critical for hybrid strategy)
            total.value = response.data.total ?? newOptions.length;

            // Append or replace
            if (append) {
                options.value = [...options.value, ...newOptions];
            } else {
                options.value = newOptions;
            }

            // Determine if more pages exist
            const hasNext = !!response.data.next_page_url ||
                           (response.data.current_page && response.data.last_page &&
                            response.data.current_page < response.data.last_page);

            hasMore.value = hasNext;

        } catch (err: any) {
            error.value =
                err.response?.data?.error ||
                err.response?.data?.message ||
                'Failed to load options. Please try again.';

            console.error('[useAsyncOptions] Fetch failed:', err);
        } finally {
            loading.value = false;
        }
    };

    /**
     * Debounced search – triggers fresh fetch
     */
    const search = (term: string) => {
        searchTerm.value = term.trim();

        if (debounceTimeout) clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(() => {
            fetch(searchTerm.value, false);
        }, config.delay ?? 400);
    };

    /**
     * Load next page (for infinite scroll or full-load loop)
     */
    const loadMore = () => {
        if (hasMore.value && !loading.value) {
            page.value++;
            fetch(searchTerm.value, true);
        }
    };

    /**
     * Manual refresh (e.g., pre-load selected values in edit forms)
     */
    const refresh = () => {
        fetch(searchTerm.value, false);
    };

    // React to changes in dynamic params (e.g., label_field change)
    watch(
        () => config.params,
        () => {
            refresh();
        },
        { deep: true }
    );

    return {
        // Readonly state
        options: computed(() => options.value),
        total: computed(() => total.value),       // ← New: for AsyncSelect strategy
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        hasMore: computed(() => hasMore.value),

        // Actions
        search,
        loadMore,
        refresh,
        fetch,
    };
}
