<template>
    <div class="space-y-8">

        <!-- 1. Welcome + Quick Links -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl p-6 text-white">
            <h2 class="text-2xl font-bold">Welcome, {{ user.name }}!</h2>
            <p class="mt-1 opacity-90">You are logged in as <strong>{{ user.role }}</strong></p>

            <!-- <div class="mt-4 flex flex-wrap gap-3">
                <LinkButton href="/students" icon="pi pi-users" label="Students" />
                <LinkButton href="/staff" icon="pi pi-briefcase" label="Staff" />
                <LinkButton href="/finance" icon="pi pi-money-bill" label="Finance" />
                <LinkButton href="/settings" icon="pi pi-cog" label="Settings" />
            </div> -->
        </div>

        <!-- 2. Core Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Statistic v-for="card in cards" :key="card.title" v-bind="card" />
        </div>

        <!-- 3. Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <DoughnutChart title="Staff by Department" :labels="charts.staff_dept.labels"
                :data="charts.staff_dept.data" />
            <LineChart title="Enrollment Trend (YTD)" :labels="charts.enrollment.labels"
                :data="charts.enrollment.data" />
        </div>

        <!-- 4. Recent Activity -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-3">Recent Activity</h3>
            <ul class="space-y-2">
                <li v-for="log in recentLogs" :key="log.id" class="flex items-center gap-3 text-sm">
                    <i :class="log.icon"></i>
                    <span>{{ log.description }}</span>
                    <span class="text-gray-500 text-xs">{{ log.time }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LineChart from '@/Components/widgets/LineChart.vue'
import DoughnutChart from '@/Components/widgets/DoughnutChart.vue'
import Statistic from '@/Components/widgets/Statistic.vue'

const page = usePage()
const props = page.props

console.log(props);


const user = computed(() => ({
    name: props.auth.user.name,
    role: props.auth.user.getPrimaryCategory?.() ?? 'User',
}))

defineProps({
    cards: Array,
    charts: Object,
    recentLogs: Array,
})
</script>
