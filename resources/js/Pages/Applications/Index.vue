<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  applications: any;
  filters: Record<string, any>;
  applicationsRequired?: boolean;
  feeConfig?: Record<string, any>;
}>();

const rows = computed(() => props.applications?.data ?? props.applications ?? []);
</script>

<template>
  <Head title="Applications" />
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Applications</h1>
        <p class="text-sm text-muted-foreground">
          Pre-admission applications. Approval does not enroll the candidate.
        </p>
      </div>
    </div>

    <div class="rounded-lg border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="p-3">Number</th>
            <th class="p-3">Candidate</th>
            <th class="p-3">Status</th>
            <th class="p-3">Source</th>
            <th class="p-3">Submitted</th>
            <th class="p-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="app in rows" :key="app.id" class="border-t">
            <td class="p-3 font-mono text-xs">{{ app.application_number }}</td>
            <td class="p-3">{{ app.full_name || `${app.first_name} ${app.last_name}` }}</td>
            <td class="p-3 capitalize">{{ app.status }}</td>
            <td class="p-3">{{ app.source }}</td>
            <td class="p-3">{{ app.submitted_at ? new Date(app.submitted_at).toLocaleDateString() : '—' }}</td>
            <td class="p-3 text-right">
              <Link :href="`/applications/${app.id}`" class="text-primary underline">View</Link>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="p-6 text-center text-muted-foreground">No applications found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
