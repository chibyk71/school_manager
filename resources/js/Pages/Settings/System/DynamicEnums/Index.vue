<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from 'primevue'

interface Definition {
  id: string
  key: string
  label: string
  description: string | null
}

defineProps<{
  definitions: Definition[]
  canManage: boolean
  canManageGlobals: boolean
}>()
</script>

<template>
  <Head title="Dynamic Enums" />
  <AuthenticatedLayout>
    <template #header>
      <h1 class="text-xl font-semibold">Dynamic Enums</h1>
      <p class="text-sm text-surface-500 mt-1">
        Application-defined vocabularies. Keys are fixed; administrators configure values and presentation.
      </p>
    </template>

    <div class="p-4 space-y-4">
      <div
        v-if="!definitions.length"
        class="rounded border border-surface-200 p-6 text-center text-surface-500"
      >
        No Dynamic Enum definitions are registered yet.
      </div>

      <div class="overflow-x-auto rounded border border-surface-200">
        <table class="min-w-full text-sm">
          <thead class="bg-surface-50 text-left">
            <tr>
              <th class="px-4 py-3 font-medium">Label</th>
              <th class="px-4 py-3 font-medium">Key</th>
              <th class="px-4 py-3 font-medium">Description</th>
              <th class="px-4 py-3 font-medium w-28">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="def in definitions"
              :key="def.id"
              class="border-t border-surface-100 hover:bg-surface-50"
            >
              <td class="px-4 py-3 font-medium">{{ def.label }}</td>
              <td class="px-4 py-3 font-mono text-xs text-surface-600">{{ def.key }}</td>
              <td class="px-4 py-3 text-surface-600">{{ def.description || '—' }}</td>
              <td class="px-4 py-3">
                <Link :href="route('settings.system.dynamic-enums.show', def.key)">
                  <Button label="Configure" size="small" text />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
