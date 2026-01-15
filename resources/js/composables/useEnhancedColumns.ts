// resources/js/composables/useEnhancedColumns.ts
/**
 * useEnhancedColumns.ts
 *
 * Reusable composable to take base columns (from backend or static) and apply
 * targeted enhancements/overrides to specific columns by their field name.
 *
 * Core purpose:
 * • Avoid repeating the same column customization logic across multiple Index pages
 * • Allow fine-grained modifications (custom render, badges, avatars, actions, width, etc.)
 * • Return a new computed column array — original columns remain untouched
 * • Fully type-safe with generics
 * • Works with your AdvancedDataTable and ColumnDefinition type
 *
 * Usage pattern (in any Index.vue):
 *
 * const { enhancedColumns } = useEnhancedColumns({
 *   baseColumns: props.columns,   // from Inertia props or static
 *   overrides: {
 *     full_name: enhanceFullNameWithAvatar(),
 *     is_active: enhanceStatusToggle({ toggleHandler }),
 *     actions: enhanceActionsDropdown({ actions: userActions }),
 *     type: enhanceTypeBadge(),
 *     // add more field-specific overrides...
 *   }
 * })
 *
 * <AdvancedDataTable :columns="enhancedColumns" ... />
 */

import { computed, type ComputedRef } from 'vue'
import type { ColumnDefinition } from '@/types/datatables'

// ────────────────────────────────────────────────
// Type for overrides map — field name → enhancement function/result
// ────────────────────────────────────────────────
type ColumnOverride<T> =
    | Partial<ColumnDefinition<T>>
    // | ((base: ColumnDefinition<T>) => Partial<ColumnDefinition<T>>)

// ────────────────────────────────────────────────
// Main composable
// ────────────────────────────────────────────────
export function useEnhancedColumns<T = any>(
    /** Base columns coming from backend (props.columns) or static definition */
    baseColumns: ColumnDefinition<T>[],

    /** Map of field → override or enhancement function */
    overrides: Record<string, ColumnOverride<T>>) {

    const enhancedColumns = computed<ColumnDefinition<T>[]>(() => {
        // Normalize input (ref or array)
        const original = Array.isArray(baseColumns) ? baseColumns : []

        // Create a mutable copy — we never mutate the original
        const cols = [...original]

        // Helper to find and replace or push
        const upsert = (field: string, newCol: Partial<ColumnDefinition<T>>) => {
            const index = cols.findIndex(c => c.field === field)
            if (index >= 0) {
                cols[index] = { ...cols[index], ...newCol }
            } else {
                cols.push({ field, header: field, ...newCol } as ColumnDefinition<T>)
            }
        }

        // Apply each override
        for (const [field, override] of Object.entries(overrides)) {
            upsert(field, override)
        }

        return cols
    })

    return {
        enhancedColumns
    }
}
