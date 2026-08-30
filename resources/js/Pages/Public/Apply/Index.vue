<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  schoolName?: string;
  sessions?: Array<{ id: string; name: string }>;
  feeConfig?: Record<string, any>;
}>();

const form = useForm({
  academic_session_id: props.sessions?.[0]?.id ?? '',
  first_name: '',
  last_name: '',
  middle_name: '',
  date_of_birth: '',
  gender: '',
  phone: '',
  email: '',
  guardians_data: [{ name: '', phone: '', email: '', relationship: 'parent', is_primary: true }],
  source: 'public_portal',
});

function submit() {
  form.post('/apply');
}
</script>

<template>
  <Head title="Apply" />
  <div class="mx-auto max-w-xl p-6 space-y-4">
    <h1 class="text-2xl font-semibold">Apply{{ schoolName ? ` — ${schoolName}` : '' }}</h1>
    <p class="text-sm text-muted-foreground">No account required. Submitting does not enroll the candidate.</p>

    <form class="space-y-3" @submit.prevent="submit">
      <label class="block text-sm">Session
        <select v-model="form.academic_session_id" class="mt-1 w-full rounded border p-2" required>
          <option v-for="s in sessions || []" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </label>
      <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm">First name<input v-model="form.first_name" class="mt-1 w-full rounded border p-2" required /></label>
        <label class="block text-sm">Last name<input v-model="form.last_name" class="mt-1 w-full rounded border p-2" required /></label>
      </div>
      <label class="block text-sm">Date of birth<input type="date" v-model="form.date_of_birth" class="mt-1 w-full rounded border p-2" /></label>
      <label class="block text-sm">Email<input type="email" v-model="form.email" class="mt-1 w-full rounded border p-2" /></label>
      <label class="block text-sm">Phone<input v-model="form.phone" class="mt-1 w-full rounded border p-2" /></label>
      <fieldset class="rounded border p-3 space-y-2">
        <legend class="text-sm font-medium px-1">Applicant / guardian</legend>
        <input v-model="form.guardians_data[0].name" class="w-full rounded border p-2 text-sm" placeholder="Name" required />
        <input v-model="form.guardians_data[0].phone" class="w-full rounded border p-2 text-sm" placeholder="Phone" required />
        <input v-model="form.guardians_data[0].email" class="w-full rounded border p-2 text-sm" placeholder="Email" />
        <input v-model="form.guardians_data[0].relationship" class="w-full rounded border p-2 text-sm" placeholder="Relationship" />
      </fieldset>
      <p v-if="feeConfig?.required" class="text-sm text-amber-700">An application fee may be required. Payment is handled by Finance.</p>
      <button class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" :disabled="form.processing">Submit application</button>
    </form>
  </div>
</template>
