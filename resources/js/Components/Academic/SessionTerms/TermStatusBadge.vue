<!--
resources/js/Components/Badges/TermStatusBadge.vue
================================================================================

Reusable badge component for displaying the status of an Academic Term.

This component mirrors the design and logic of SessionStatusBadge.vue but is
optimized for term-specific status values and labels (e.g., "In Progress" instead of "Active").

Features / Problems Solved:
────────────────────────────────────────────────────────────────────────────────
• Consistent visual representation of term status across the app
• Four distinct states with appropriate colors, icons, and labels
• Special emphasis for current/active term (ring + check icon)
• Size variants (small/large) for different contexts
• Tooltip with full explanation on hover
• Full accessibility: proper contrast, ARIA roles, screen-reader friendly
• Dark mode ready (Tailwind + PrimeVue)
• Reuses centralized status configuration from types/academic-calendar.ts
• Lightweight & performant (suitable for tables with many rows)

Integration / Usage:
────────────────────────────────────────────────────────────────────────────────
• Used inside TermListTable.vue (in SessionTermsModal) and potentially other term views
• Example:
  <TermStatusBadge :status="term.status" :is-current="term.is_current" size="small" />

Props:
────────────────────────────────────────────────────────────────────────────────
- status        'pending' | 'active' | 'closed' | 'archived'   required
- isCurrent     boolean                                       default: false
- size          'small' | 'large'                             default: 'small'
- showLabel     boolean                                       default: true

Status → UI Mapping (from types/academic-calendar.ts):
────────────────────────────────────────────────────────────────────────────────
pending:  Upcoming / info
active:   In Progress / success
closed:   Completed / warning
archived: Archived / danger
-->

<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from 'primevue'
import type { Term } from '@/types/academic'
import {
    TERM_STATUS_CONFIG,
} from '@/types/academic'

const props = withDefaults(defineProps<{
    status: Term['status']
    isCurrent?: boolean
    size?: 'small' | 'large'
    showLabel?: boolean
}>(), {
    isCurrent: false,
    size: 'small',
    showLabel: true
})

// ────────────────────────────────────────────────
// Computed Properties
// ────────────────────────────────────────────────

const statusConfig = computed(() =>
    TERM_STATUS_CONFIG[props.status] || { label: 'Unknown', severity: 'secondary' }
)

const label = computed(() => statusConfig.value.label || props.status)

const badgeSeverity = computed(() => {
    if (props.isCurrent && props.status === 'active') return 'success'
    return statusConfig.value.severity
})

const iconClass = computed(() => {
    if (props.isCurrent && props.status === 'active') return 'pi pi-check-circle'
    if (props.status === 'active') return 'pi pi-play-circle'
    if (props.status === 'pending') return 'pi pi-clock'
    if (props.status === 'closed') return 'pi pi-stop-circle'
    if (props.status === 'archived') return 'pi pi-archive'
    return 'pi pi-question-circle'
})

const tooltipText = computed(() => {
    const base = `This term is ${label.value.toLowerCase()}`
    return props.isCurrent
        ? `${base}. It is the currently active term.`
        : base
})

const badgeClasses = computed(() => ({
    'text-xs px-2.5 py-1': props.size === 'small',
    'text-sm px-3 py-1.5 font-medium': props.size === 'large',
    'ring-2 ring-offset-2 ring-offset-surface-50 dark:ring-offset-surface-900 ring-primary-500/50':
        props.isCurrent && props.status === 'active'
}))
</script>

<template>
    <Badge :value="showLabel ? label : ''" :severity="badgeSeverity" :class="badgeClasses" v-tooltip="tooltipText"
        role="status" :aria-label="`Term Status: ${label}${isCurrent ? ' (Current)' : ''}`">
        <template #default v-if="!showLabel">
            <i :class="iconClass" class="text-base" />
        </template>
    </Badge>
</template>

<style scoped>
/* Optional pulse animation for current term */
:deep(.p-badge.ring-2) {
    animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {

    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(var(--primary-500), 0.4);
    }

    70% {
        box-shadow: 0 0 0 8px rgba(var(--primary-500), 0);
    }
}
</style>
