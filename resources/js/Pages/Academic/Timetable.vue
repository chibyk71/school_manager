<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, Column, DataTable } from 'primevue';
import TimeTable from '../Profile/StudentTab/TimeTable.vue';


</script>

<template>
    <AuthenticatedLayout title="Subjects" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'Subjects' }]" :buttons="[{icon:'ti ti-refresh', severity:'secondary'},{ label: 'Add Subjects', icon: 'ti ti-school' }]">

    <TimeTable />
    </AuthenticatedLayout>
</template>
