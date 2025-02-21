<script lang="ts" setup>
    import { type Statistic } from '@/types';
    import { Avatar, Badge, Card } from 'primevue';

    defineProps<Statistic&{
        image: string,
        severity: string
    }>()
</script>

<template>
    <Card class="flex-1 animate-card border-0">
        <template #content>
            <div class="flex items-center">
                <Avatar :image :class="severity" class='mr-2 p-1.5' size="large" />
                <div class="overflow-hidden flex-1">
                    <div class="flex items-center justify-between">
                        <h2 class="counter text-xl/none font-semibold">{{ stat }}</h2>
                        <Badge severity="danger">{{ percentage }}%</Badge>
                    </div>
                    <p class="text-sm/none font-normal">{{ title }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between border-t mt-3 p-1">
                <p class="mb-0">Active : <span class="text-color font-semibold">{{ active }}</span></p>
                <span class="text-gray-700 dark:text-surface-200">|</span>
                <p>Inactive : <span class="text-color font-semibold">{{ inactive }}</span></p>
            </div>
        </template>
    </Card>
</template>

