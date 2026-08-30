<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  admissions: any;
  filters: Record<string, any>;
}>();

const rows = computed(() => props.admissions?.data ?? props.admissions ?? []);

function candidateName(row: any): string {
  if (row.application) {
    return row.application.full_name || `${row.application.first_name ?? ''} ${row.application.last_name ?? ''}`.trim();
  }
  const c = row.configs?.candidate;
  if (c) return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || '—';
  if (row.student) return `${row.student.first_name} ${row.student.last_name}`;
  return '—';
}
</script>

<template>
  <Head title="Admissions" />
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Admissions</h1>
        <p class="text-sm text-muted-foreground">
          School offers of a place. Acceptance does not create enrollment (Phase 4).
        </p>
      </div>
    </div>

    <div class="rounded-lg border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="p-3">Candidate</th>
            <th class="p-3">Application</th>
            <th class="p-3">Class</th>
            <th class="p-3">Session</th>
            <th class="p-3">Status</th>
            <th class="p-3">Offered</th>
            <th class="p-3">Deadline</th>
            <th class="p-3">Registration</th>
            <th class="p-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="p-3">{{ candidateName(row) }}</td>
            <td class="p-3 font-mono text-xs">{{ row.application?.application_number || '—' }}</td>
            <td class="p-3">{{ row.class_level?.name || row.classLevel?.name || '—' }}</td>
            <td class="p-3">{{ row.academic_session?.name || row.academicSession?.name || '—' }}</td>
            <td class="p-3 capitalize">{{ row.status }}</td>
            <td class="p-3">{{ row.offered_at ? new Date(row.offered_at).toLocaleDateString() : '—' }}</td>
            <td class="p-3">{{ row.acceptance_deadline ? new Date(row.acceptance_deadline).toLocaleString() : '—' }}</td>
            <td class="p-3">
              <span v-if="row.registration_date">{{ row.registration_date }}</span>
              <span v-else-if="row.registration_starts_at || row.registration_ends_at">
                {{ row.registration_starts_at ? new Date(row.registration_starts_at).toLocaleDateString() : '…' }}
                –
                {{ row.registration_ends_at ? new Date(row.registration_ends_at).toLocaleDateString() : '…' }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="p-3 text-right">
              <Link :href="`/admissions/offers/${row.id}`" class="text-primary underline">View</Link>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="9" class="p-6 text-center text-muted-foreground">No admissions found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
