<script setup lang="ts">
defineProps<{
    departments: { id: number; name: string }[];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-1">
        <span
            v-for="dept in departments"
            :key="dept.id"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"
        >
            {{ dept.name }}
        </span>
        <span v-if="!departments?.length" class="text-gray-400 text-xs">—</span>
    </div>
</template>