<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TimeTable from '../Profile/StudentTab/TimeTable.vue';


</script>

<template>
    <AuthenticatedLayout title="Time Table"
        :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Time Table' }]"
        :buttons="[{ icon: 'ti ti-refresh', severity: 'secondary' }, { label: 'Add Session', icon: 'ti ti-clock-alt' }]">

        <TimeTable />
    </AuthenticatedLayout>
</template>
