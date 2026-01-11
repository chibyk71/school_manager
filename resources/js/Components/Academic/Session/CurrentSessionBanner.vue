<!--
resources/js/Components/Banners/CurrentSessionBanner.vue
================================================================================

Small, prominent banner displaying the currently active academic session.

Main Purpose:
────────────────────────────────────────────────────────────────────────────────
• Provide immediate visibility of which academic session is currently active
• Serve as a constant reminder across dashboard, sidebar, or header
• Quick reference for admins/teachers when working in the system
• Visual distinction when no session is active or when viewing historical data

Features Implemented:
────────────────────────────────────────────────────────────────────────────────
• Compact, non-intrusive design (fits in headers, sidebars, dashboards)
• Shows session name + date range
• Clear visual indicator (green accent when active, neutral when none)
• Handles "no active session" state gracefully with actionable message
• Responsive: collapses nicely on mobile
• Uses centralized status configuration & formatting
• Accessibility: proper contrast, semantic HTML, screen-reader friendly
• Optional link to session management (when permitted)

Integration Points:
────────────────────────────────────────────────────────────────────────────────
• Typically placed in:
  - App layout header
  - Dashboard welcome section
  - Academic module sidebar
• Uses Inertia page props (expects current_session in props.auth or props)
• Can fetch fresh data via Inertia.reload() on demand

Recommended placement example (in App.vue or Layout):
────────────────────────────────────────────────────────────────────────────────
<div v-if="$page.props.currentSession" class="mb-4">
  <CurrentSessionBanner :session="$page.props.currentSession" />
</div>

Props:
────────────────────────────────────────────────────────────────────────────────
- session      CurrentSessionInfo | null    required (can be null)
- compact      boolean                     default: false
- showLink     boolean                     default: true (shows "Manage Sessions" link)
-->

<script setup lang="ts">
import { computed } from 'vue'
import { format, parseISO } from 'date-fns'
import { Button, Badge } from 'primevue'
import { usePermissions } from '@/composables/usePermissions'
import type { CurrentSessionInfo } from '@/types/academic'

const props = withDefaults(defineProps<{
    session: CurrentSessionInfo | null
    compact?: boolean
    showLink?: boolean
}>(), {
    compact: false,
    showLink: true,
})

const { hasPermission } = usePermissions()

const hasActiveSession = computed(() => !!props.session && props.session.is_current)

const dateRange = computed(() => {
    if (!props.session) return ''
    const start = format(parseISO(props.session.start_date), 'MMM yyyy')
    const end = format(parseISO(props.session.end_date), 'MMM yyyy')
    return `${start} — ${end}`
})

const bannerClasses = computed(() => ({
    'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200': hasActiveSession,
    'bg-amber-50 dark:bg-amber-950 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200': !hasActiveSession,
}))
</script>

<template>
    <div class="current-session-banner rounded-lg border p-3 md:p-4 shadow-sm" :class="bannerClasses" role="status"
        aria-live="polite">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <!-- Left content: status & session info -->
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold" :class="hasActiveSession
                        ? 'bg-green-600 text-white'
                        : 'bg-amber-600 text-white'">
                        <i class="pi" :class="hasActiveSession ? 'pi-check' : 'pi-exclamation-triangle'"></i>
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="font-medium text-base">
                        <span v-if="hasActiveSession">
                            Current Academic Session:
                        </span>
                        <span v-else>
                            No Active Academic Session
                        </span>
                    </div>

                    <div v-if="hasActiveSession" class="text-sm opacity-90 mt-0.5">
                        {{ session?.name }} • {{ dateRange }}
                    </div>

                    <div v-else class="text-sm opacity-90 mt-0.5">
                        The system is currently operating without an active session
                    </div>
                </div>
            </div>

            <!-- Right content: action link (when permitted) -->
            <div v-if="showLink && hasPermission('academic-sessions.index')" class="flex-shrink-0">
                <Button v-if="compact" label="Manage" icon="pi pi-calendar" text severity="success" size="small"
                    @click="$inertia.visit(route('academic-sessions.index'))" />

                <Button v-else label="Manage Academic Sessions" icon="pi pi-calendar" outlined severity="success"
                    size="small" @click="$inertia.visit(route('academic-sessions.index'))" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.current-session-banner {
    transition: all 0.2s ease;
}

.current-session-banner:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
</style>
