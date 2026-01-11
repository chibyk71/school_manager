<!--
resources/js/Components/Timeline/SessionTimeline.vue
================================================================================

Visual chronological timeline of Academic Sessions

Main Purpose:
────────────────────────────────────────────────────────────────────────────────
• Provide administrators with a clear visual overview of academic years
• Show sequence, duration, status and current session at a glance
• Help identify gaps, overlaps or unusual patterns in the calendar
• Serve as dashboard widget or dedicated "Academic Calendar Overview" section

Features:
────────────────────────────────────────────────────────────────────────────────
• Horizontal timeline layout (responsive → becomes scrollable on mobile)
• Color-coded bars based on session status
• Prominent highlight for current/active session
• Hover tooltips with key information
• Shows approximate duration (in months)
• Handles future & past sessions naturally
• Responsive: collapses gracefully on small screens
• Accessible: proper ARIA roles and labels
• Uses centralized status configuration from types file

Props:
────────────────────────────────────────────────────────────────────────────────
- sessions       Array<AcademicSession>   required
- showCurrentOnly boolean                 default false
- maxHeight      string                   default '320px'

Usage Example:
────────────────────────────────────────────────────────────────────────────────
<SessionTimeline
  :sessions="academicSessions"
  :showCurrentOnly="false"
  class="mt-6"
/>
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { format, differenceInMonths, parseISO, isAfter, isBefore, addMonths } from 'date-fns'
import type { AcademicSession } from '@/types/academic'
import {
    SESSION_STATUS_CONFIG,
} from '@/types/academic'

const props = defineProps<{
    sessions: AcademicSession[]
    showCurrentOnly?: boolean
    maxHeight?: string
}>()

const timelineRef = ref<HTMLElement | null>(null)

// ────────────────────────────────────────────────
// Prepare sorted sessions (oldest → newest)
// ────────────────────────────────────────────────

const sortedSessions = computed(() =>
    [...props.sessions]
        .filter(s => !props.showCurrentOnly || s.is_current)
        .sort((a, b) =>
            parseISO(a.start_date).getTime() - parseISO(b.start_date).getTime()
        )
)

const hasSessions = computed(() => sortedSessions.value.length > 0)

// Find min & max dates for timeline scaling
const overallStart = computed(() => {
    if (!hasSessions.value) return new Date()
    return parseISO(sortedSessions.value[0].start_date)
})

const overallEnd = computed(() => {
    if (!hasSessions.value) return new Date()
    return parseISO(sortedSessions.value[sortedSessions.value.length - 1].end_date)
})

const totalMonths = computed(() => {
    return Math.max(1, differenceInMonths(overallEnd.value, overallStart.value) + 1)
})

// ────────────────────────────────────────────────
// Calculate position & width for each session bar
// ────────────────────────────────────────────────

const getPosition = (session: AcademicSession) => {
    const start = parseISO(session.start_date)
    const monthsFromBegin = differenceInMonths(start, overallStart.value)
    return (monthsFromBegin / totalMonths.value) * 100
}

const getWidth = (session: AcademicSession) => {
    const start = parseISO(session.start_date)
    const end = parseISO(session.end_date)
    const months = differenceInMonths(end, start) + 1
    return (months / totalMonths.value) * 100
}

const getStatusConfig = (status: AcademicSession['status']) =>
    SESSION_STATUS_CONFIG[status] || { label: 'Unknown', severity: 'secondary' }

// ────────────────────────────────────────────────
// Tooltip content
// ────────────────────────────────────────────────

const getTooltip = (s: AcademicSession) => {
    const start = format(parseISO(s.start_date), 'MMM yyyy')
    const end = format(parseISO(s.end_date), 'MMM yyyy')
    const months = differenceInMonths(parseISO(s.end_date), parseISO(s.start_date)) + 1

    return [
        s.name,
        `${start} — ${end} (${months} months)`,
        `Status: ${getStatusConfig(s.status).label}`,
        s.is_current ? '✓ Current Active Session' : ''
    ].filter(Boolean).join('\n')
}
</script>

<template>
    <div class="session-timeline-container">
        <!-- Header -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">
                Academic Sessions Timeline
            </h3>

            <div v-if="hasSessions" class="text-sm text-surface-500 dark:text-surface-400">
                {{ format(overallStart, 'MMM yyyy') }} — {{ format(overallEnd, 'MMM yyyy') }}
            </div>
        </div>

        <!-- No sessions placeholder -->
        <div v-if="!hasSessions"
            class="text-center py-10 text-surface-500 dark:text-surface-400 border border-dashed border-surface-300 dark:border-surface-600 rounded-lg">
            No academic sessions found
        </div>

        <!-- Timeline -->
        <div v-else ref="timelineRef"
            class="relative h-28 md:h-32 bg-surface-100 dark:bg-surface-900 rounded-lg overflow-x-auto border border-surface-200 dark:border-surface-700"
            :style="{ maxHeight: maxHeight }">
            <!-- Background year markers (approximate) -->
            <div
                class="absolute inset-0 flex justify-between px-4 text-xs text-surface-400 dark:text-surface-500 pointer-events-none">
                <span v-for="i in 5" :key="i" class="opacity-40">{{ format(addMonths(overallStart,
                    Math.round(totalMonths / 4) * i), 'yyyy') }}</span>
            </div>

            <!-- Session bars -->
            <div v-for="session in sortedSessions" :key="session.id"
                class="absolute h-14 md:h-16 top-1/2 -translate-y-1/2 rounded-md transition-all duration-200 hover:scale-105 hover:z-10"
                :style="{
                    left: `${getPosition(session)}%`,
                    width: `${getWidth(session)}%`,
                    minWidth: '80px'
                }" v-tooltip="getTooltip(session)" role="button" tabindex="0" :aria-label="`Session: ${session.name}`">
                <div class="h-full rounded-md shadow-sm flex items-center justify-center text-xs font-medium text-white relative overflow-hidden"
                    :class="[
                        session.is_current
                            ? 'ring-2 ring-offset-2 ring-offset-surface-50 dark:ring-offset-surface-900 ring-primary-500'
                            : '',
                        getStatusConfig(session.status).severity === 'success' ? 'bg-green-600' :
                            getStatusConfig(session.status).severity === 'warning' ? 'bg-orange-600' :
                                getStatusConfig(session.status).severity === 'danger' ? 'bg-red-600' :
                                    'bg-blue-600'
                    ]">
                    <!-- Current indicator pulse -->
                    <div v-if="session.is_current" class="absolute inset-0 bg-white opacity-10 animate-pulse"></div>

                    <span class="relative z-10 truncate px-2">
                        {{ session.name }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap gap-4 text-sm justify-center md:justify-start">
            <div v-for="(config, status) in SESSION_STATUS_CONFIG" :key="status" class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full" :class="{
                    'bg-green-500': status === 'active',
                    'bg-orange-500': status === 'closed',
                    'bg-red-500': status === 'archived',
                    'bg-blue-500': status === 'pending'
                }"></div>
                <span class="text-surface-600 dark:text-surface-300">
                    {{ config.label }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.session-timeline-container {
    @apply w-full;
}

.timeline-bar {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>
