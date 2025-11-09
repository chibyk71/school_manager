<!-- resources/js/components/widgets/StudentsCount.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import BaseWidget from './BaseWidget.vue';

defineProps<{
    total: number;
    primary: number;
    secondary: number;
}>();
</script>

<template>
    <BaseWidget title="Students" icon="pi-users" endpoint="/api/dashboard/students-count" :refresh-interval="300000">
        <template #default="{ data }">
            <div class="grid grid-cols-1 gap-4 text-center">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <div class="text-3xl font-bold text-blue-900">{{ data.total }}</div>
                    <div class="text-sm text-blue-700">Total Enrolled</div>
                </div>

                <div class="flex justify-around text-sm">
                    <div>
                        <span class="font-semibold">{{ data.primary }}</span> Primary
                    </div>
                    <div>
                        <span class="font-semibold">{{ data.secondary }}</span> Secondary
                    </div>
                </div>
            </div>
        </template>
    </BaseWidget>
</template>