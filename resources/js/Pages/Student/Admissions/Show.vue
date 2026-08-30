<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  admission: any;
}>();

const declineForm = useForm({ reason: '' });
const cancelForm = useForm({ reason: '' });
const deadlineForm = useForm({
  acceptance_deadline: props.admission.acceptance_deadline ?? '',
  registration_date: props.admission.registration_date ?? '',
  registration_starts_at: props.admission.registration_starts_at ?? '',
  registration_ends_at: props.admission.registration_ends_at ?? '',
});

const active = ['offered', 'pending'].includes(props.admission.status);

function postAction(path: string, data: Record<string, any> = {}) {
  router.post(`/admissions/${props.admission.id}/${path}`, data, { preserveScroll: true });
}

function submitDecline() {
  declineForm.post(`/admissions/${props.admission.id}/decline`, { preserveScroll: true });
}
function submitCancel() {
  cancelForm.post(`/admissions/${props.admission.id}/cancel`, { preserveScroll: true });
}
function submitDeadlines() {
  deadlineForm.patch(`/admissions/${props.admission.id}/deadlines`, { preserveScroll: true });
}

function candidateName(): string {
  const a = props.admission;
  if (a.application) {
    return a.application.full_name || `${a.application.first_name ?? ''} ${a.application.last_name ?? ''}`.trim();
  }
  const c = a.configs?.candidate;
  if (c) return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || '—';
  if (a.student) return `${a.student.first_name} ${a.student.last_name}`;
  return '—';
}
</script>

<template>
  <Head title="Admission offer" />
  <div class="p-6 space-y-6 max-w-3xl">
    <div class="flex items-center justify-between">
      <div>
        <Link href="/admissions/offers" class="text-sm text-muted-foreground underline">← Admissions</Link>
        <h1 class="text-2xl font-semibold mt-1">Admission offer</h1>
        <p class="text-sm text-muted-foreground capitalize">Status: {{ admission.status }}</p>
      </div>
    </div>

    <div class="rounded-lg border p-4 space-y-2 text-sm">
      <div><span class="text-muted-foreground">Candidate:</span> {{ candidateName() }}</div>
      <div v-if="admission.application">
        <span class="text-muted-foreground">Application:</span>
        {{ admission.application.application_number }}
      </div>
      <div>
        <span class="text-muted-foreground">Class:</span>
        {{ admission.class_level?.name || admission.classLevel?.name || '—' }}
      </div>
      <div>
        <span class="text-muted-foreground">Session:</span>
        {{ admission.academic_session?.name || admission.academicSession?.name || '—' }}
      </div>
      <div>
        <span class="text-muted-foreground">Offered at:</span>
        {{ admission.offered_at ? new Date(admission.offered_at).toLocaleString() : '—' }}
      </div>
      <div>
        <span class="text-muted-foreground">Acceptance deadline:</span>
        {{ admission.acceptance_deadline ? new Date(admission.acceptance_deadline).toLocaleString() : 'None' }}
      </div>
      <div>
        <span class="text-muted-foreground">Registration date:</span>
        {{ admission.registration_date || '—' }}
      </div>
      <div>
        <span class="text-muted-foreground">Registration window:</span>
        {{ admission.registration_starts_at ? new Date(admission.registration_starts_at).toLocaleString() : '—' }}
        –
        {{ admission.registration_ends_at ? new Date(admission.registration_ends_at).toLocaleString() : '—' }}
      </div>
      <div v-if="admission.notes"><span class="text-muted-foreground">Notes:</span> {{ admission.notes }}</div>
      <div class="text-xs text-muted-foreground pt-2">
        Enrollment is not created in this phase. Student remains optional until Phase 4.
      </div>
    </div>

    <div v-if="active" class="flex flex-wrap gap-2">
      <button type="button" class="px-3 py-1.5 rounded bg-primary text-primary-foreground text-sm" @click="postAction('accept')">
        Accept
      </button>
      <button type="button" class="px-3 py-1.5 rounded border text-sm" @click="submitDecline">
        Decline
      </button>
      <button type="button" class="px-3 py-1.5 rounded border text-sm" @click="submitCancel">
        Cancel
      </button>
      <button type="button" class="px-3 py-1.5 rounded border text-sm" @click="postAction('expire')">
        Force expire check
      </button>
    </div>

    <div v-if="active" class="rounded-lg border p-4 space-y-3">
      <h2 class="font-medium">Update deadlines / registration window</h2>
      <div class="grid gap-2 sm:grid-cols-2 text-sm">
        <label class="space-y-1">
          <span class="text-muted-foreground">Acceptance deadline</span>
          <input v-model="deadlineForm.acceptance_deadline" type="datetime-local" class="w-full border rounded px-2 py-1" />
        </label>
        <label class="space-y-1">
          <span class="text-muted-foreground">Registration date</span>
          <input v-model="deadlineForm.registration_date" type="date" class="w-full border rounded px-2 py-1" />
        </label>
        <label class="space-y-1">
          <span class="text-muted-foreground">Registration starts</span>
          <input v-model="deadlineForm.registration_starts_at" type="datetime-local" class="w-full border rounded px-2 py-1" />
        </label>
        <label class="space-y-1">
          <span class="text-muted-foreground">Registration ends</span>
          <input v-model="deadlineForm.registration_ends_at" type="datetime-local" class="w-full border rounded px-2 py-1" />
        </label>
      </div>
      <button type="button" class="px-3 py-1.5 rounded border text-sm" @click="submitDeadlines">Save</button>
    </div>
  </div>
</template>
