<script setup lang="ts">
/**
 * ClassSectionStatusBadge.vue
 *
 * Reusable display badge for a class section's status and enrollment capacity.
 *
 * ── What It Shows ─────────────────────────────────────────────────────────────
 * Combines two pieces of information into a compact badge pair:
 *   1. Status badge    → "Active" (green) | "Inactive" (gray) | "Archived" (red)
 *   2. Capacity badge  → "Full" (red) | "32 / 40" (amber) | "Uncapped" (blue)
 *                        Only shown when showCapacity = true (default: false)
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────
 * Simple status only (DataTable rows):
 *   <ClassSectionStatusBadge :section="row" />
 *
 * Status + capacity (detail panel or enrollment forms):
 *   <ClassSectionStatusBadge :section="section" show-capacity />
 *
 * ── Props ─────────────────────────────────────────────────────────────────────
 * section        ClassSection — the section to display badges for
 * showCapacity   boolean      — whether to show the enrollment capacity badge
 * size           'sm' | 'md'  — badge size variant (default: 'sm')
 *
 * ── Fits Into The Module ──────────────────────────────────────────────────────
 * Used by:
 *   - Index.vue DataTable (status column render function)
 *   - ClassSectionFormModal.vue header area
 *   - BulkGenerateModal.vue preview step (shows generated section state)
 *   - Any parent module that shows section context (timetable, results, etc.)
 */

import { computed } from 'vue'
import type { ClassSection } from '@/types/class-section'

const props = defineProps<{
    section: Pick<
        ClassSection,
        | 'status'
        | 'is_active'
        | 'is_trashed'
        | 'capacity'
        | 'is_uncapped'
        | 'is_at_capacity'
        | 'students_count'
        | 'remaining_capacity'
    >
    showCapacity?: boolean
    size?: 'sm' | 'md'
}>()

const size = computed(() => props.size ?? 'sm')

// ── Status badge config ──────────────────────────────────────────────────────
const statusConfig = computed(() => {
    if (props.section.is_trashed) {
        return { label: 'Archived', classes: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }
    }
    if (props.section.is_active) {
        return { label: 'Active', classes: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }
    }
    return { label: 'Inactive', classes: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }
})

// ── Capacity badge config ─────────────────────────────────────────────────────
const capacityConfig = computed(() => {
    if (props.section.is_uncapped) {
        return {
            label: 'Uncapped',
            classes: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        }
    }
    if (props.section.is_at_capacity) {
        return {
            label: 'Full',
            classes: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        }
    }
    // Show enrolled / capacity when students_count is available
    if (props.section.students_count !== undefined) {
        const pct = (props.section.students_count / props.section.capacity) * 100
        const isNearFull = pct >= 80

        return {
            label: `${props.section.students_count} / ${props.section.capacity}`,
            classes: isNearFull
                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        }
    }
    return {
        label: `Cap: ${props.section.capacity}`,
        classes: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    }
})

const sizeClasses = computed(() =>
    size.value === 'md'
        ? 'px-2.5 py-1 text-xs font-medium rounded-full'
        : 'px-2 py-0.5 text-xs font-medium rounded-full'
)
</script>

<template>
    <span class="inline-flex items-center gap-1.5">
        <!-- Status badge -->
        <span :class="[sizeClasses, statusConfig.classes]">
            {{ statusConfig.label }}
        </span>

        <!-- Capacity badge — only when showCapacity is true -->
        <span
            v-if="showCapacity"
            :class="[sizeClasses, capacityConfig.classes]"
        >
            {{ capacityConfig.label }}
        </span>
    </span>
</template>
