<script setup lang="ts">
    defineProps<{
        label: string;
        description: string;
    }>()
</script>

<template>
	<div
        class="flex justify-between items-center flex-wrap bg-white dark:bg-[--app-header-bg] border rounded p-3 pb-0 mb-3">
        <div class="mb-3  text-color">
            <h6>{{ label }}</h6>
            <p class="dark:text-slate-100">{{ description }}</p>
        </div>
        <div class="mb-3">
            <slot></slot>
        </div>
    </div>
</template>
