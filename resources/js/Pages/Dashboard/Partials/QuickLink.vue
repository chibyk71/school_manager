<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import { Avatar } from 'primevue';

    defineProps<{
        url: string,
        icon: string,
        label: string,
        sevierity: string,
        borderClass: string,
        bgClass: string
    }>()
</script>

<template>
    <Link :href="url" :class="`block ${sevierity} rounded-lg p-2 text-center mb-3`">
        <Avatar size="large" shape="circle" :class="`border ${borderClass} ${bgClass} p-1.5`">
            <template #icon>
                <span :class="`inline-flex items-center justify-center size-full ${bgClass} rounded-full`"><i :class="icon"></i></span>
            </template>
        </Avatar>
        <p class="text-surface-700">{{ label }}</p>
    </Link>
</template>