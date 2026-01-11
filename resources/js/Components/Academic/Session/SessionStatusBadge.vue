<!--
resources/js/Components/Badges/SessionStatusBadge.vue
================================================================================

Reusable badge component for displaying Academic Session or Term status.

Purpose / When to use:
────────────────────────────────────────────────────────────────────────────────
• Display status of sessions or terms in tables, cards, modals, lists
• Provide consistent visual language for status across the entire academic module
• Quick recognition of state: upcoming, active/current, closed, archived
• Works for both AcademicSession and Term entities

Features Implemented:
────────────────────────────────────────────────────────────────────────────────
• Uses centralized status configuration from types/academic-calendar.ts
• Four distinct visual states with appropriate colors & icons
• Special prominent styling for "current/active" sessions
• Supports both session and term status (same enum values)
• Compact & large size variants
• Tooltip showing full status explanation (hover help)
• Full accessibility (ARIA role, proper contrast ratios)
• Dark mode ready (Tailwind + PrimeVue)
• Very lightweight – suitable for tables with 100+ rows

Usage Examples:
────────────────────────────────────────────────────────────────────────────────
<!-- In DataTable column -->
<SessionStatusBadge :status="row.status" :is-current="row.is_current" />

<!-- In card or detail view -->
<SessionStatusBadge status="active" :is-current="true" size="large" showLabel />

Props:
────────────────────────────────────────────────────────────────────────────────
- status 'pending' | 'active' | 'closed' | 'archived' required
- isCurrent boolean default: false
- size 'small' | 'large' default: 'small'
- showLabel boolean default: true (shows text)
- entityType 'session' | 'term' default: 'session' (affects tooltip wording)

Integration Notes:
────────────────────────────────────────────────────────────────────────────────
• Relies on SESSION_STATUS_CONFIG & TERM_STATUS_CONFIG from types
• Designed to be used inside AdvancedDataTable body slot or cards
• Colors chosen for good visibility in both light & dark themes
-->

<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from 'primevue'
import type { AcademicSession } from '@/types/academic'
import {
    SESSION_STATUS_CONFIG,
    TERM_STATUS_CONFIG,
} from '@/types/academic'

const props = withDefaults(defineProps<{
    status: AcademicSession['status']
    isCurrent?: boolean
    size?: 'small' | 'large'
    showLabel?: boolean
    entityType?: 'session' | 'term'
}>(), {
    isCurrent: false,
    size: 'small',
    showLabel: true,
    entityType: 'session'
})

const statusConfig = computed(() => {
    const configs = props.entityType === 'term' ? TERM_STATUS_CONFIG : SESSION_STATUS_CONFIG
    return configs[props.status] || { label: 'Unknown', severity: 'secondary' }
})

const label = computed(() => {
    if (props.entityType === 'term') {
        return TERM_STATUS_CONFIG[props.status].label || props.status
    }
    return SESSION_STATUS_CONFIG[props.status].label || props.status
})

const badgeSeverity = computed(() => {
    // Special case: current session gets extra visual weight
    if (props.isCurrent && props.status === 'active') {
        return 'success'
    }
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
    const base = props.entityType === 'session'
        ? `This academic session is ${label.value.toLowerCase()}`
        : `This term is ${label.value.toLowerCase()}`

    return props.isCurrent
        ? `${base}. It is the currently active ${props.entityType}.`
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
        role="status" :aria-label="`Status: ${label}${isCurrent ? ' (Current)' : ''}`">
        <template #default v-if="!showLabel">
            <i :class="iconClass" class="text-base" />
        </template>
    </Badge>
</template>

<style scoped>
/* Optional extra styling for current/active emphasis */
:deep(.p-badge) {
    transition: all 0.2s ease;
}

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
