<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps<{ found?: boolean; application?: any }>();
</script>

<template>
  <Head title="Application status" />
  <div class="mx-auto max-w-lg p-6 space-y-3">
    <h1 class="text-2xl font-semibold">Application status</h1>
    <template v-if="found && application">
      <p class="font-mono">{{ application.application_number || application.data?.application_number }}</p>
      <p class="capitalize">Status: {{ application.status || application.data?.status }}</p>
      <p class="text-sm text-muted-foreground">Approval of an application does not mean the candidate is enrolled.</p>
    </template>
    <p v-else class="text-muted-foreground">No application found for that token.</p>
  </div>
</template>
