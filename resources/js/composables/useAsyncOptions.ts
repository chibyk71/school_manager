import { ref, computed } from 'vue';
import axios from 'axios';

export function useAsyncOptions(config: {
    url: string;
    params?: Record<string, any>;
    searchKey?: string;
    delay?: number;
}) {
    const options = ref<any[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const page = ref(1);
    const searchTerm = ref('');
    const hasMore = ref(true);

    let timeout: any;

    const fetch = async (term = '', append = false) => {
        if (!append) {
            page.value = 1;
            options.value = [];
        }

        clearTimeout(timeout);
        loading.value = true;
        error.value = null;

        try {
            const params: any = {
                page: page.value,
                [config.searchKey ?? 'search']: term || undefined,
                ...config.params,
            };

            const res = await axios.get(config.url, { params });
            const newOptions = res.data.data || res.data;

            if (append) {
                options.value = [...options.value, ...newOptions];
            } else {
                options.value = newOptions;
            }

            hasMore.value = !!res.data.next_page_url;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to load options';
            console.error(err);
        } finally {
            loading.value = false;
        }
    };

    const search = (term: string) => {
        searchTerm.value = term;
        clearTimeout(timeout);
        timeout = setTimeout(() => fetch(term), config.delay ?? 400);
    };

    const loadMore = () => {
        if (hasMore.value && !loading.value) {
            page.value++;
            fetch(searchTerm.value, true);
        }
    };

    return { options, loading, error, search, loadMore, hasMore, fetch };
}
