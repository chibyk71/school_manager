<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  application: any;
  possibleDuplicates?: any;
  canReview?: boolean;
  canApprove?: boolean;
  canReject?: boolean;
}>();

const app = props.application?.data ?? props.application;
const rejectForm = useForm({ rejection_reason: '' });
const approveForm = useForm({ admin_notes: '' });

function beginReview() {
  router.post(`/applications/${app.id}/review`);
}
function approve() {
  approveForm.post(`/applications/${app.id}/approve`);
}
function reject() {
  rejectForm.post(`/applications/${app.id}/reject`);
}
</script>

<template>
  <Head :title="`Application ${app.application_number}`" />
  <div class="p-6 space-y-6 max-w-4xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <Link href="/applications" class="text-sm text-primary underline">← Applications</Link>
        <h1 class="text-2xl font-semibold mt-2">{{ app.full_name }}</h1>
        <p class="font-mono text-sm">{{ app.application_number }} · <span class="capitalize">{{ app.status }}</span></p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="canReview && ['submitted'].includes(app.status)" class="rounded-md border px-3 py-2 text-sm" @click="beginReview">Begin review</button>
        <button v-if="canApprove && ['submitted','under_review'].includes(app.status)" class="rounded-md bg-green-600 px-3 py-2 text-sm text-white" @click="approve">Approve</button>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <section class="rounded-lg border p-4 space-y-2">
        <h2 class="font-medium">Candidate</h2>
        <p>DOB: {{ app.date_of_birth || '—' }}</p>
        <p>Gender: {{ app.gender || '—' }}</p>
        <p>Phone: {{ app.phone || '—' }}</p>
        <p>Email: {{ app.email || '—' }}</p>
      </section>
      <section class="rounded-lg border p-4 space-y-2">
        <h2 class="font-medium">Applicant / contact</h2>
        <div v-for="(g, i) in (app.guardians_data || [])" :key="i" class="text-sm">
          <p>{{ g.name }} ({{ g.relationship }})</p>
          <p>{{ g.phone }} · {{ g.email }}</p>
        </div>
        <p v-if="!(app.guardians_data || []).length" class="text-muted-foreground text-sm">No guardian data on file.</p>
      </section>
    </div>

    <section v-if="(possibleDuplicates?.data || possibleDuplicates || []).length" class="rounded-lg border border-amber-300 bg-amber-50 p-4">
      <h2 class="font-medium text-amber-900">Possible duplicates (warning only)</h2>
      <ul class="mt-2 text-sm space-y-1">
        <li v-for="d in (possibleDuplicates?.data || possibleDuplicates)" :key="d.id">
          <Link :href="`/applications/${d.id}`" class="underline">{{ d.application_number }}</Link>
          — {{ d.full_name }} ({{ d.status }})
        </li>
      </ul>
    </section>

    <section v-if="canReject && ['submitted','under_review'].includes(app.status)" class="rounded-lg border p-4 space-y-3">
      <h2 class="font-medium">Reject application</h2>
      <textarea v-model="rejectForm.rejection_reason" class="w-full rounded border p-2 text-sm" rows="3" placeholder="Reason (required)" />
      <button class="rounded-md bg-red-600 px-3 py-2 text-sm text-white" :disabled="rejectForm.processing" @click="reject">Reject</button>
    </section>

    <p class="text-xs text-muted-foreground">
      Application approval means review passed only. It does not create Admission, Student, or Enrollment records.
    </p>
  </div>
</template>
