<!-- resources/js/components/widgets/BaseWidget.vue -->
<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted } from 'vue';
import Card from 'primevue/card';
import ProgressSpinner from 'primevue/progressspinner';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    title: string;
    icon?: string;
    endpoint: string;          // Inertia route that returns JSON
    refreshInterval?: number; // ms, optional auto-refresh
}>();

const emit = defineEmits<{
    (e: 'loaded', payload: any): void;
}>();

const toast = useToast();
const loading = ref(true);
const error = ref<string | null>(null);
const data = ref<any>(null);

const fetch = async () => {
    loading.value = true;
    error.value = null;
    try {
        // Inertia.visit keeps the page but returns JSON when `preserveState: true`
        const response = await window.axios.get(props.endpoint);
        data.value = response.data;
        emit('loaded', response.data);
    } catch (e: any) {
        error.value = e.response?.data?.message ?? 'Failed to load widget';
        toast.add({ severity: 'error', summary: props.title, detail: error.value, life: 4000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetch();
    if (props.refreshInterval) {
        const id = setInterval(fetch, props.refreshInterval);

        // cleanup on unmount
        onUnmounted(() => clearInterval(id));
    }
});

watch(() => props.endpoint, fetch);
</script>

<template>
    <Card class="h-full shadow-md">
        <template #title>
            <div class="flex items-center gap-2">
                <i v-if="icon" :class="`pi ${icon}`"></i>
                <span class="font-medium">{{ title }}</span>
            </div>
        </template>

        <template #content>
            <div v-if="loading" class="flex justify-center items-center h-32">
                <ProgressSpinner style="width:2rem;height:2rem" />
            </div>

            <div v-else-if="error" class="text-red-600 text-sm">
                {{ error }}
                <Button icon="pi pi-refresh" class="p-button-text p-button-sm ml-2" @click="fetch" />
            </div>

            <slot v-else :data="data" />
        </template>
    </Card>
</template>

<style scoped>
:deep(.p-card) {
    @apply bg-white dark:bg-gray-800;
}
</style>