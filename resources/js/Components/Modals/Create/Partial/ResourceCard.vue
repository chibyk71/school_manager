<script setup lang="ts">
import { Avatar, Card } from 'primevue';

defineProps<{
    header: string,
    icon: string,
}>()
</script>

<template>
    <Card class="mb-4">
        <template #content>
            <div class="add-section">
                <slot></slot>
            </div>
        </template>
        <template #header>
            <div class="flex items-center bg-gray-300/50 p-2.5 px-5 rounded-t-lg">
                <Avatar :icon='icon' />
                <h4 class="text-dark ml-2 ">{{ header }}</h4>
            </div>
        </template>
    </Card>
</template>
