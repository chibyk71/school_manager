<!--
resources/js/Components/Cards/SessionCard.vue
================================================================================

Compact card component for displaying a summary of an Academic Session.

Main Use Cases:
────────────────────────────────────────────────────────────────────────────────
• Dashboard / Overview widgets showing current/upcoming sessions
• Grid or list view of all sessions (alternative to full DataTable)
• Quick preview cards when selecting parent session
• Visual summary in reports or analytics sections

Features Implemented:
────────────────────────────────────────────────────────────────────────────────
• Clean, modern card layout with Tailwind + PrimeVue
• Visual status indicator (colored badge + icon)
• Highlights if session is CURRENT
• Responsive: adjusts nicely from mobile to desktop
• Shows key dates in a consistent, readable format
• Optional subtle progress bar (percentage through session)
• Hover/tap effects for better interactivity
• Full accessibility (ARIA labels, proper contrast)
• Uses centralized status configuration from types file

Integration:
────────────────────────────────────────────────────────────────────────────────
• Expects full or partial AcademicSession object
• Uses SESSION_STATUS_CONFIG from types/academic-calendar.ts
• Can be used standalone or inside v-for loops
• Designed to work well in PrimeVue Card / custom layouts

Props:
────────────────────────────────────────────────────────────────────────────────
- session: AcademicSession | SessionOption (required)
- showProgress: boolean (default: false) - shows % progress
- clickable: boolean (default: false) - adds hover/click styles

Example usage:
────────────────────────────────────────────────────────────────────────────────
<SessionCard
  :session="currentSession"
  :showProgress="true"
  class="w-full md:w-80"
/>
-->

<script setup lang="ts">
import { computed } from 'vue'
import { format, differenceInDays, parseISO } from 'date-fns'
import { Badge, ProgressBar } from 'primevue'
import type { AcademicSession, SessionOption } from '@/types/academic'
import {
    SESSION_STATUS_CONFIG,
} from '@/types/academic'

const props = defineProps<{
    session: AcademicSession
    showProgress?: boolean
    clickable?: boolean
}>()

const statusConfig = computed(() =>
    SESSION_STATUS_CONFIG[props.session.status] || { label: 'Unknown', severity: 'secondary' }
)

const durationDays = computed(() => {
    const start = parseISO(props.session.start_date)
    const end = parseISO(props.session.end_date)
    return differenceInDays(end, start) + 1
})

const daysPassed = computed(() => {
    const start = parseISO(props.session.start_date)
    const today = new Date()
    return differenceInDays(today, start)
})

const progress = computed(() => {
    if (!props.showProgress || durationDays.value <= 0) return 0
    const percentage = Math.min(100, Math.max(0, Math.round((daysPassed.value / durationDays.value) * 100)))
    return percentage
})

const isCurrent = computed(() => props.session.is_current === true)

const dateRangeText = computed(() => {
    const start = format(parseISO(props.session.start_date), 'MMM dd, yyyy')
    const end = format(parseISO(props.session.end_date), 'MMM dd, yyyy')
    return `${start} — ${end}`
})
</script>

<template>
    <div class="session-card relative overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 shadow-sm transition-all duration-200 hover:shadow-md"
        :class="{
            'cursor-pointer hover:border-primary-500/50': clickable,
            'ring-2 ring-primary-500/30': isCurrent
        }" role="article" :aria-label="`Academic Session: ${session.name}`">
        <!-- Top accent bar (color based on status) -->
        <div class="absolute top-0 left-0 right-0 h-1" :class="statusConfig.severity === 'success' ? 'bg-green-500' :
            statusConfig.severity === 'warning' ? 'bg-orange-500' :
                statusConfig.severity === 'danger' ? 'bg-red-500' :
                    'bg-blue-500'"></div>

        <div class="p-5 pt-6">
            <!-- Header with name & badge -->
            <div class="flex justify-between items-start gap-3 mb-3">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 line-clamp-2">
                    {{ session.name }}
                </h3>

                <Badge :value="statusConfig.label" :severity="statusConfig.severity"
                    class="text-xs uppercase font-medium" />
            </div>

            <!-- Current tag (prominent when active) -->
            <div v-if="isCurrent" class="mb-3">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 text-xs font-medium">
                    <i class="pi pi-check-circle text-sm"></i>
                    CURRENT SESSION
                </span>
            </div>

            <!-- Date range -->
            <div class="text-sm text-surface-600 dark:text-surface-400 mb-4">
                <i class="pi pi-calendar mr-2 text-sm"></i>
                {{ dateRangeText }}
            </div>

            <!-- Progress bar (optional) -->
            <div v-if="showProgress" class="mb-3">
                <div class="flex justify-between text-xs text-surface-500 dark:text-surface-400 mb-1.5">
                    <span>Progress</span>
                    <span>{{ progress }}%</span>
                </div>
                <ProgressBar :value="progress" :showValue="false" class="h-2" :pt="{
                    root: { class: 'bg-surface-200 dark:bg-surface-700' },
                    value: { class: progress >= 90 ? 'bg-red-500' : progress >= 70 ? 'bg-orange-500' : 'bg-primary-500' }
                }" />
            </div>

            <!-- Footer info -->
            <div class="text-xs text-surface-500 dark:text-surface-400 flex justify-between items-center">
                <span>
                    <i class="pi pi-calendar-times mr-1.5"></i>
                    {{ durationDays }} days
                </span>
                <span v-if="session.terms_count !== undefined">
                    <i class="pi pi-list mr-1.5"></i>
                    {{ session.terms_count }} term{{ session.terms_count === 1 ? '' : 's' }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.session-card {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.session-card:hover {
    transform: translateY(-2px);
}
</style>
