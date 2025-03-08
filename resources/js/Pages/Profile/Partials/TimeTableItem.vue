<script setup lang="ts">
import { Avatar, Badge } from 'primevue';
import { ref } from 'vue';

defineProps<{
    type: 'student' | 'teacher',
    time: string,
    subject: string,
    room?: string,
    teacherImage?: string,
    teacherName?: string
}>()

const color = ref(['bg-primary-200/30', 'bg-red-200/30', 'bg-yellow-200/30', 'bg-sky-200/30', 'bg-green-200/30']);
</script>

<template>
    <div v-if="type === 'teacher'" class="rounded p-3 mb-4 border">
        <div class="pb-3 border-b">
            <Badge severity="danger" value="Room No:104" />
        </div>
        <span class="text-dark block py-2 text-nowrap"><span class="text-sm">Subject :</span> Spanish</span>
        <p class="text-dark text-nowrap text-sm"><i class="ti ti-clock mr-1"></i>09:45 -10:30 AM
        </p>
    </div>
    <div v-else-if="type === 'student'" :class="color[Math.floor(Math.random() * 5)]" class="rounded p-3 mb-4">
        <p class="flex items-center text-nowrap mb-1">
            <i class="ti ti-clock mr-1"></i>{{ time }}
        </p>
        <p class="text-dark text-nowrap">Subject : {{ subject }}</p>
        <div class="bg-surface-0 rounded p-1 mt-3">
            <Link href="teacher-details.html" class="text-muted flex items-center gap-x-2">
            <Avatar :image="teacherImage" />
            {{ teacherName }}
            </Link>
        </div>
    </div>
</template>
