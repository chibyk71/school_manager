<!-- resources/js/components/widgets/DoughnutChart.vue -->
<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { usePage } from '@inertiajs/vue3'
import { ApexOptions } from 'apexcharts'

/* ------------------------------------------------------------------ */
/* Props                                                              */
/* ------------------------------------------------------------------ */
interface DoughnutChartProps {
    title?: string
    labels: string[]
    data: number[]
    height?: string
}
const props = defineProps<DoughnutChartProps>()

/* ------------------------------------------------------------------ */
/* ApexCharts config                                                  */
/* ------------------------------------------------------------------ */
const chartOptions = ref<ApexOptions>({
    chart: {
        type: 'donut',
        height: props.height || 280,
        fontFamily: 'Inter, sans-serif',
        foreColor: '#6b7280', // gray-500
        toolbar: { show: false },
    },
    colors: [
        '#3b82f6', // blue-500
        '#10b981', // emerald-500
        '#f59e0b', // amber-500
        '#ef4444', // red-500
        '#8b5cf6', // violet-500
        '#ec4899', // pink-500
    ],
    labels: props.labels,
    legend: {
        position: 'bottom',
        horizontalAlign: 'center',
        fontSize: '13px',
        markers: { size: 10, shape: 'circle' },
        itemMargin: { horizontal: 8, vertical: 4 },
    },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    name: { show: false },
                    value: {
                        show: true,
                        fontSize: '20px',
                        fontWeight: 600,
                        color: '#1f2937',
                        formatter: (val: number|string) => String(val),
                    },
                    total: {
                        show: true,
                        showAlways: true,
                        label: 'Total',
                        fontSize: '14px',
                        fontWeight: 600,
                        color: '#6b7280',
                        formatter: (w: any) =>
                            w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0),
                    },
                },
            },
        },
    },
    responsive: [
        {
            breakpoint: 480,
            options: { chart: { height: 220 }, legend: { position: 'bottom' } },
        },
    ],
    tooltip: {
        y: { formatter: (val: number) => `${val}` },
    },
    theme: {
        mode: computed(() => (usePage().props.darkMode ? 'dark' : 'light')).value,
    },
})

const series = ref<number[]>(props.data)

/* ------------------------------------------------------------------ */
/* React to prop changes                                              */
/* ------------------------------------------------------------------ */
watch(
    [() => props.labels, () => props.data],
    ([newLabels, newData]: [string[], number[]]) => {
        chartOptions.value = { ...chartOptions.value, labels: newLabels }
        series.value = newData
    },
    { deep: true }
)
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Optional Title -->
        <h3 v-if="title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3 px-2">
            {{ title }}
        </h3>

        <!-- Chart -->
        <VueApexCharts type="donut" :options="chartOptions" :series="series" class="flex-1" />
    </div>
</template>
