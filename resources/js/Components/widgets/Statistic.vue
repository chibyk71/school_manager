<!-- resources/js/components/widgets/Statistic.vue -->
<script setup lang="ts">
/**
 * ----------------------------------------------------------------------
 *  Statistic Widget – displays a card with count, % change, active/inactive.
 * ----------------------------------------------------------------------
 * Works with the new Metrics API:
 *   • endpoint = `/api/stats?model=student&type=value&filters[status]=active`
 *   • Returns: { value: 1234, growth: 2.1 }
 *
 * @example
 *   <Statistic
 *     title="Active Students"
 *     endpoint="/api/stats?model=student&type=value&filters[status]=active"
 *     icon="pi pi-users"
 *     :refresh-interval="60000"
 *   />
 */

import { StatisticData } from '@/types';
import BaseWidget from './BaseWidget.vue';
import { Avatar, Badge, Card } from 'primevue';

/* ------------------------------------------------------------------ */
/*  Props – only what BaseWidget needs                               */
/* ------------------------------------------------------------------ */
interface StatisticProps {
    title: string;
    icon?: string;
    endpoint: string;
    refreshInterval?: number;
}
defineProps<StatisticProps>();
</script>

<template>
    <BaseWidget :title="title" :icon="icon" :endpoint="endpoint" :refresh-interval="refreshInterval">
        <template #default="{ data }: { data: StatisticData }">
            <Card class="flex-1 animate-card border-0 h-full">
                <template #content>
                    <div class="flex items-center">
                        <!-- Avatar – fallback to default icon -->
                        <Avatar
                            :image="data.image ?? '/assets/img/icons/default.svg'"
                            :class="data.severity ?? 'bg-blue-200/50'"
                            class="mr-2 p-1.5"
                            size="large"
                        />

                        <div class="overflow-hidden flex-1">
                            <!-- Main stat + growth badge -->
                            <div class="flex items-center justify-between">
                                <h2 class="counter text-xl/none font-semibold">
                                    {{ data.value }}
                                </h2>
                                <Badge
                                    :severity="data.growth && data.growth >= 0 ? 'success' : 'danger'"
                                    class="text-xs"
                                >
                                    {{ data.growth ? `${data.growth > 0 ? '+' : ''}${data.growth}%` : '0%' }}
                                </Badge>
                            </div>

                            <!-- Title -->
                            <p class="text-sm/none font-normal">{{ title }}</p>
                        </div>
                    </div>

                    <!-- Active / Inactive (optional) -->
                    <div v-if="data.active !== undefined || data.inactive !== undefined"
                         class="flex items-center justify-between border-t mt-3 p-1 text-sm">
                        <p class="mb-0">
                            Active: <span class="font-semibold">{{ data.active ?? 0 }}</span>
                        </p>
                        <span class="text-gray-500">|</span>
                        <p class="mb-0">
                            Inactive: <span class="font-semibold">{{ data.inactive ?? 0 }}</span>
                        </p>
                    </div>
                </template>
            </Card>
        </template>
    </BaseWidget>
</template>

<style scoped>
.animate-card {
    @apply transition-all duration-300;
}
</style>
