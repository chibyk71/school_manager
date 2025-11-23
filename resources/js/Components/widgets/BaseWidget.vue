<!-- resources/js/components/widgets/BaseWidget.vue -->
<script setup lang="ts" generic="T = any">
/**
 * ----------------------------------------------------------------------
 *  BaseWidget – reusable card that fetches JSON from an Inertia route.
 * ----------------------------------------------------------------------
 * Features
 *   • Auto-refresh (optional)
 *   • Loading / error states with retry button
 *   • Emits `loaded` (payload) and `error` (message)
 *   • Fully typed slot (`data: T`)
 *
 * @example
 *   <BaseWidget
 *     title="Enrollment"
 *     icon="pi pi-users"
 *     endpoint="/api/widgets/enrollment"
 *     :refresh-interval="30000"
 *   >
 *     <template #default="{ data }">
 *       <p>Total: {{ data.total }}</p>
 *     </template>
 *   </BaseWidget>
 */

import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import Card from 'primevue/card';
import ProgressSpinner from 'primevue/progressspinner';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import axios, { type AxiosError } from 'axios';

/* ------------------------------------------------------------------ */
/*  Props (typed)                                                    */
/* ------------------------------------------------------------------ */
interface BaseWidgetProps {
    /** Card title */
    title: string;
    /** Optional PrimeIcons class (e.g. "pi pi-users") */
    icon?: string;
    /** Inertia route that returns JSON */
    endpoint: string;
    /** Auto-refresh interval in ms (optional) */
    refreshInterval?: number;
}
const props = defineProps<BaseWidgetProps>();

/* ------------------------------------------------------------------ */
/*  Emits                                                            */
/* ------------------------------------------------------------------ */
const emit = defineEmits<{
    /** Successfully loaded data */
    (e: 'loaded', payload: T): void;
    /** Fetch error */
    (e: 'error', message: string): void;
}>();

/* ------------------------------------------------------------------ */
/*  Reactive state                                                   */
/* ------------------------------------------------------------------ */
const toast = useToast();
const loading = ref(true);
const error = ref<string | null>(null);
const data = ref<T | null>(null);
let intervalId: number | null = null;

/* ------------------------------------------------------------------ */
/*  Core fetch logic                                                */
/* ------------------------------------------------------------------ */
const fetch = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get<T>(props.endpoint);
        data.value = response.data;
        emit('loaded', response.data);
    } catch (e) {
        const err = e as AxiosError<{ message?: string }>;
        const msg = err.response?.data?.message ?? err.message ?? 'Failed to load widget';
        error.value = msg;
        toast.add({ severity: 'error', summary: props.title, detail: msg, life: 4000 });
        emit('error', msg);
    } finally {
        loading.value = false;
    }
};

/* ------------------------------------------------------------------ */
/*  Auto-refresh handling                                            */
/* ------------------------------------------------------------------ */
const startInterval = () => {
    if (!props.refreshInterval) return;
    intervalId = window.setInterval(fetch, props.refreshInterval);
};

const stopInterval = () => {
    if (intervalId !== null) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

/* ------------------------------------------------------------------ */
/*  Lifecycle                                                        */
/* ------------------------------------------------------------------ */
onMounted(() => {
    fetch();
    startInterval();
});

onBeforeUnmount(() => {
    stopInterval();
});

/* ------------------------------------------------------------------ */
/*  React to endpoint changes (e.g. filter change)                  */
/* ------------------------------------------------------------------ */
watch(() => props.endpoint, () => {
    fetch();
});

/* ------------------------------------------------------------------ */
/*  React to refreshInterval changes                                 */
/* ------------------------------------------------------------------ */
watch(
    () => props.refreshInterval,
    (newVal, oldVal) => {
        stopInterval();
        if (newVal) startInterval();
    }
);

/* ------------------------------------------------------------------ */
/*  Computed – slot props                                            */
/* ------------------------------------------------------------------ */
const slotProps = computed(() => ({
    data: data.value.data,
}));
</script>

<template>
    <!-- --------------------------------------------------------------
       Card wrapper – full height, dark-mode aware
       -------------------------------------------------------------- -->
    <Card class="h-full flex flex-col shadow-md bg-white dark:bg-gray-800">
        <!-- -------------------------- Title -------------------------- -->
        <template #title>
            <div class="flex items-center gap-2">
                <i v-if="icon" :class="`pi ${icon}`"></i>
                <span class="font-medium">{{ title }}</span>
            </div>
        </template>

        <!-- -------------------------- Content ----------------------- -->
        <template #content>
            <div class="flex-1 flex flex-col justify-center">

                <!-- Loading -->
                <div v-if="loading" class="flex justify-center items-center h-32" aria-live="polite">
                    <ProgressSpinner style="width:2rem;height:2rem" />
                </div>

                <!-- Error -->
                <div v-else-if="error" class="text-red-600 text-sm flex items-center flex-wrap gap-2"
                    aria-live="assertive">
                    {{ error }}
                    <Button icon="pi pi-refresh" class="p-button-text p-button-sm" @click="fetch" aria-label="Retry" />
                </div>

                <!-- Success – slot receives typed data -->
                <slot v-else v-bind="slotProps" />
            </div>
        </template>
    </Card>
</template>

<style scoped>
/* No deep selector needed – Card already respects dark mode via Tailwind */
</style>
