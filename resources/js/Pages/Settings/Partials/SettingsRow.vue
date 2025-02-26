<script setup lang="ts">
    defineProps<{
        label: string;
        description: string;
    }>()
</script>

<template>
	<div
        class="flex flex-row justify-between flex-wrap p-card pt-3 px-4 pb-0 mb-3">
        <div class="mb-3  text-color">
            <h6 class="text-lg font-medium">{{ label }}</h6>
            <p class="dark:text-slate-100">{{ description }}</p>
        </div>
        <div class="mb-3">
            <slot></slot>
        </div>
    </div>
</template>
