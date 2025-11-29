// resources/js/composables/useModalForm.ts
import { useForm, type InertiaForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { modals } from '@/helpers'
import { router } from '@inertiajs/vue3'
import type { ResourceData } from '@/helpers'

interface ModalFormOptions<T extends Record<string, any>> {
    /** Initial form data (optional) */
    initialData?: Partial<T>

    /** Custom submit URL (overrides route generation) */
    url?: string

    /** HTTP method */
    method?: 'post' | 'put' | 'patch' | 'delete'

    /** Route name prefix (e.g. 'users', 'departments') */
    resource?: string

    /** Resource ID for update */
    resourceId?: string | number

    /** Custom success message */
    successMessage?: string

    /** Pages to reload after success */
    reload?: string[]

    /** Callback on success */
    onSuccess?: () => void

    /** Callback on error */
    onError?: (errors: Record<string, string>) => void
}

/**
 * The single source of truth for ALL modal forms.
 * Replaces useForm + toast + modals.close + router.reload in every modal.
 */
export function useModalForm<T extends Record<string, any>>(
    initialValues: T = {} as T,
    options: ModalFormOptions<T> = {}
) {
    const toast = useToast()

    // Create the Inertia form
    const form = useForm<T>({
        ...initialValues,
        ...(options.initialData || {}),
    })

    // Reset form when modal opens (optional future enhancement)
    // watchEffect(() => {
    //   if (modals.items.length > 0) form.reset()
    // })

    const submit = () => {
        const method = options.method || (options.resourceId ? 'put' : 'post')
        const url = options.url || route(
            options.resourceId
                ? `${options.resource}.update`
                : `${options.resource}.store`,
            options.resourceId
        )

        // Clear previous errors
        form.clearErrors()

        // Submit
        form[method](url, {
            preserveScroll: true,
            onSuccess: () => {
                const message = options.successMessage || 'Operation completed successfully'
                toast.add({ severity: 'success', summary: 'Success', detail: message, life: 4000 })

                // Close modal
                modals.close()

                // Reload data if needed
                if (options.reload?.length) {
                    router.reload({ only: options.reload })
                }

                // Custom callback
                options.onSuccess?.()
            },
            onError: (errors) => {
                console.error('Form submission failed:', errors)

                // Show first error
                const firstError = Object.values(errors)[0]
                toast.add({
                    severity: 'error',
                    summary: 'Validation Failed',
                    detail: firstError || 'Please check the form',
                    life: 5000,
                })

                options.onError?.(errors)
            },
        })
    }

    return {
        form: form as InertiaForm<T> & { processing: boolean },
        submit,
        isLoading: form.processing,
        errors: form.errors,
        hasErrors: form.hasErrors,
        clearErrors: form.clearErrors,
        reset: form.reset,
    }
}
