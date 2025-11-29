<!-- resources/js/Components/Modals/ResourceDialog.vue -->
<script setup lang="ts">
import { computed, watch, onMounted, onBeforeUnmount, defineComponent } from 'vue'
import { defineAsyncComponent } from 'vue'
import { useToast } from 'primevue/usetoast'
import { modals } from '@/helpers'
import { ModalComponentDirectory } from '@/Components/Modals/ModalDirectory'
import { Dialog, ProgressSpinner } from 'primevue'

// Toast for dev errors
const toast = useToast()

// Current modal (first in queue)
const current = computed(() => modals.items[0] ?? null)
const modalId = computed(() => current.value?.id)
const payload = computed(() => current.value?.data ?? {})

// Dynamic component loader
const ModalComponent = computed(() => {
    if (!modalId.value) return null

    const loader = ModalComponentDirectory[modalId.value]
    if (!loader) {
        console.error(`[ResourceDialog] No modal registered for ID: "${modalId.value}"`)
        toast.add({
            severity: 'error',
            summary: 'Modal Not Found',
            detail: `Modal "${modalId.value}" is not registered in ModalDirectory.ts`,
            life: 8000,
        })
        return null
    }

    return defineAsyncComponent({
        loader,
        loadingComponent: defineComponent({
            template: `
                <div class="text-center py-16 text-red-600">
                    <i class="pi pi-spinner spinner-border text-6xl mb-4"></i>
                    <p>Loading...</p>
                </div>
            `,
        }),
        errorComponent: defineComponent({
            template: `
                <div class="text-center py-16 text-red-600">
                    <i class="pi pi-exclamation-triangle text-6xl mb-4"></i>
                    <p>Failed to load modal component.</p>
                </div>
            `,
        }),
        delay: 100,
        timeout: 30000,
    })
})

// Close current modal
const closeModal = () => {
    modals.close()
}

// Force re-render when modal changes
let key = 0
watch(
    () => modals.items.length,
    (len, oldLen) => {
        if (len === 0 && oldLen > 0) key++ // Reset on close
        if (len > oldLen) key++          // New modal opened
    }
)
</script>

<template>
    <Dialog v-if="current" :key="key" :visible="true" :modal="true" :closable="true" :dismissable-mask="true"
        :block-scroll="true" :close-on-escape="true" @update:visible="closeModal" class="max-w-4xl w-full mx-4" :pt="{
            root: { class: 'rounded-xl shadow-2xl' },
            header: { class: 'text-xl font-bold' },
            content: { class: 'p-6' },
            footer: { class: 'border-t pt-4' },
        }">
        <!-- Header -->
        <template #header>
            <component :is="ModalComponent" v-if="ModalComponent" v-bind="payload" @close="closeModal" />
            <!-- Header is rendered by the modal component itself -->
        </template>

        <!-- Body -->
        <template #default>
            <Suspense>
                <template #default>
                    <component :is="ModalComponent" v-bind="payload" @close="closeModal" />
                </template>

                <template #fallback>
                    <div class="flex flex-col items-center justify-center py-16">
                        <ProgressSpinner />
                        <p class="mt-4 text-gray-600">Loading...</p>
                    </div>
                </template>
            </Suspense>
        </template>

        <!-- Footer (optional) -->
        <template #footer>
            <!-- Most modals handle their own footer via DynamicForm -->
            <!-- But we allow override via slot if needed -->
            <slot name="footer" />
        </template>
    </Dialog>
</template>

<style scoped>
:deep(.p-dialog) {
    max-height: 95vh;
}
</style>
