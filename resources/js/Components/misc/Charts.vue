<template>
    <Chart v-bind="props" />
</template>

<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';
import Chart from 'primevue/chart';
import { ChartProps } from 'primevue/chart';

const props = defineProps < ChartProps > ();

const emits = defineEmits(['update:data', 'update:options']);

// Add any custom logic or methods here
</script>
