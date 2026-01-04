<!-- resources/js/Pages/Settings/Partials/SettingsLayout.vue -->
<script setup lang="ts">
const { hasSidebar } = defineProps<{
    hasSidebar?: boolean;
}>()
</script>

<template>
    <div class="grid grid-cols-12 gap-4 divide-x-2">
        <div class="xxl:col-span-2 xl:col-span-3 col-span-12" v-if="hasSidebar">
            <slot name="left"></slot>
        </div>
        <div :class="hasSidebar ? 'col-span-12 xl:col-span-9 xxl:col-span-10' : 'col-span-12 flex-1'">
            <slot name="main"></slot>
        </div>
    </div>
</template>
