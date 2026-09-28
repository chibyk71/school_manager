/**
 * DataTable export composable — backend-driven.
 * Does not reconstruct filters/sorts independently; uses canonical query + target.
 */
import { ref } from 'vue'
import axios from 'axios'
import type {
    DataTableQuery,
    DataTableExportRequest,
    DataTableExportTarget,
    ExportFormat,
} from './types'

export interface UseDataTableExportOptions {
    /** POST endpoint that returns a file download */
    endpoint: string
    onError?: (error: unknown) => void
}

export function useDataTableExport(options: UseDataTableExportOptions) {
    const pending = ref(false)
    const lastError = ref<unknown>(null)

    async function exportData(
        query: DataTableQuery,
        target: DataTableExportTarget,
        columns: string[],
        format: ExportFormat = 'csv',
        filenameHint = 'export',
    ): Promise<void> {
        const body: DataTableExportRequest = {
            query,
            target,
            columns,
            format,
        }

        pending.value = true
        lastError.value = null
        try {
            const response = await axios.post(options.endpoint, body, {
                responseType: 'blob',
            })

            const disposition = response.headers['content-disposition'] as string | undefined
            let filename = `${filenameHint}.${format}`
            if (disposition) {
                const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition)
                if (match?.[1]) {
                    filename = match[1].replace(/['"]/g, '')
                }
            }

            const blob = new Blob([response.data])
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', filename)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
        } catch (e) {
            lastError.value = e
            options.onError?.(e)
            throw e
        } finally {
            pending.value = false
        }
    }

    return {
        pending,
        lastError,
        exportData,
    }
}
