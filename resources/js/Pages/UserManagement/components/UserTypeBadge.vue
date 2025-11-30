<!-- resources/js/Pages/Admin/Users/Partials/UserTypeBadge.vue -->
<script setup lang="ts">
const props = defineProps<{
    type: 'student' | 'staff' | 'guardian'
    size?: 'small' | 'normal' | 'large'
}>()

const config = {
    student: {
        label: 'Student',
        color: 'bg-blue-500 text-white',
        icon: 'pi pi-user'
    },
    staff: {
        label: 'Staff',
        color: 'bg-green-500 text-white',
        icon: 'pi pi-briefcase'
    },
    guardian: {
        label: 'Parent',
        color: 'bg-purple-500 text-white',
        icon: 'pi pi-users'
    }
}

const badge = config[props.type] || config.student
const sizeClass = {
    small: 'text-xs px-2.5 py-1',
    normal: 'text-sm px-3 py-1.5',
    large: 'text-base px-4 py-2'
}[props.size || 'normal']
</script>

<template>
    <span class="inline-flex items-center gap-1.5 font-medium rounded-full whitespace-nowrap"
        :class="[badge.color, sizeClass]">
        <i :class="badge.icon" class="text-xs" />
        {{ badge.label }}
    </span>
</template>
