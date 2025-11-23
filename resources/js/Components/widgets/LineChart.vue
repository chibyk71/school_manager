<!-- resources/js/components/widgets/LineChart.vue -->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { usePage } from '@inertiajs/vue3'
import { ApexOptions } from 'apexcharts'

interface LineChartProps {
    title?: string
    labels: string[]
    data: number[]
    height?: string
}
const props = defineProps<LineChartProps>()

const chartOptions = ref<ApexOptions>({
    chart: {
        type: 'line',
        height: props.height || 280,
        fontFamily: 'Inter, sans-serif',
        foreColor: '#6b7280',
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    stroke: {
        curve: 'smooth',
        width: 3,
    },
    colors: ['#3b82f6'],
    xaxis: {
        categories: props.labels,
        labels: { style: { fontSize: '12px' } },
    },
    yaxis: {
        labels: { formatter: (val: number) => String(Math.round(val)) },
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4,
    },
    markers: {
        size: 5,
        colors: ['#3b82f6'],
        strokeColors: '#fff',
        strokeWidth: 2,
    },
    tooltip: {
        x: { format: 'dd MMM' },
        y: { formatter: (val: number) => val.toLocaleString() },
    },
    theme: {
        mode: computed(() => (usePage().props.darkMode ? 'dark' : 'light')).value,
    },
})

const series = ref([{ name: props.title || 'Value', data: props.data }])

watch<[string[], number[]]>(
    () => [props.labels, props.data],
    ([labels, data]) => {
        chartOptions.value = { ...chartOptions.value, xaxis: { categories: labels } }
        series.value = [{ name: props.title || 'Value', data }]
    }
)
</script>

<template>
    <div class="flex flex-col h-full">
        <h3 v-if="title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3 px-2">
            {{ title }}
        </h3>

        <VueApexCharts type="line" :options="chartOptions" :series="series" class="flex-1" />
    </div>
</template>
