<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
defineProps<{
  applicationNumber?: string;
  applicationToken?: string;
  applicantName?: string;
  feeConfig?: Record<string, any>;
}>();
</script>

<template>
  <Head title="Application submitted" />
  <div class="mx-auto max-w-lg p-6 space-y-4 text-center">
    <h1 class="text-2xl font-semibold">Application received</h1>
    <p>Thank you{{ applicantName ? `, ${applicantName}` : '' }}.</p>
    <p class="font-mono text-lg">{{ applicationNumber }}</p>
    <p class="text-sm text-muted-foreground">Save your reference number. Use your private status token to check progress — the number alone is not a secret credential.</p>
    <Link v-if="applicationToken" :href="`/apply/status?token=${applicationToken}`" class="text-primary underline">Check status</Link>
  </div>
</template>
