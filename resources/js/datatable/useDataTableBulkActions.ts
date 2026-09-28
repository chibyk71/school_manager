/**
 * DataTable bulk-action composable — capability-driven, resource-agnostic.
 */
import { ref } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import axios from 'axios'
import type {
    DataTableSelection,
    DataTableBulkActionRequest,
    BulkActionResult,
    BulkActionCapability,
} from './types'

export interface UseDataTableBulkActionsOptions {
    /** POST endpoint for bulk actions, e.g. /api/students/bulk */
    endpoint: string
    /** Resource key used for TanStack Query invalidation */
    resource: string
    /** After success: clear selection */
    onSuccess?: (result: BulkActionResult) => void
    onError?: (error: unknown) => void
}

export function useDataTableBulkActions(options: UseDataTableBulkActionsOptions) {
    const queryClient = useQueryClient()
    const pending = ref(false)
    const lastResult = ref<BulkActionResult | null>(null)
    const lastError = ref<unknown>(null)

    async function execute(
        selection: DataTableSelection,
        action: string,
        payload?: unknown,
        declaredCapabilities?: BulkActionCapability[],
    ): Promise<BulkActionResult> {
        if (declaredCapabilities && !declaredCapabilities.some((c) => c.id === action)) {
            throw new Error(`Action [${action}] is not declared for this resource.`)
        }

        const body: DataTableBulkActionRequest = {
            selection,
            action,
            payload,
        }

        pending.value = true
        lastError.value = null
        try {
            const { data } = await axios.post(options.endpoint, body)
            const result = normalizeResult(data)
            lastResult.value = result

            // Invalidate entire DataTable query family for this resource
            await queryClient.invalidateQueries({
                queryKey: ['datatable', options.resource],
            })

            options.onSuccess?.(result)
            return result
        } catch (e) {
            lastError.value = e
            options.onError?.(e)
            throw e
        } finally {
            pending.value = false
        }
    }

    function normalizeResult(raw: any): BulkActionResult {
        if (raw && typeof raw.processed === 'number') {
            return {
                processed: raw.processed,
                succeeded: raw.succeeded ?? raw.count ?? 0,
                failed: raw.failed ?? 0,
                skipped: raw.skipped ?? 0,
                errors: raw.errors,
                message: raw.message,
                meta: raw.meta,
            }
        }
        // Legacy shape
        return {
            processed: raw?.count ?? 0,
            succeeded: raw?.success ? (raw?.count ?? 0) : 0,
            failed: raw?.success ? 0 : 1,
            skipped: 0,
            message: raw?.message,
            meta: raw?.meta,
        }
    }

    return {
        pending,
        lastResult,
        lastError,
        execute,
    }
}
