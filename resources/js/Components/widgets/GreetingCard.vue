<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import { Card } from 'primevue'

/**
 * Props – keep the component flexible
 * -------------------------------------------------
 * @prop {Object} user          – the entity (student/parent/teacher/admin)
 * @prop {String} role          – 'student' | 'parent' | 'teacher' | 'admin'
 * @prop {Array}  classes       – today’s timetable (student only)
 * @prop {String} notice        – teacher notice (teacher only)
 * @prop {String} updatedAt     – admin “updated recently” text
 */
const props = defineProps({
    user: { type: Object, required: true },
    role: { type: String, required: true },
    classes: { type: Array, default: () => [] },
    notice: { type: String, default: '' },
    updatedAt: { type: String, default: '' }
})

const showEditBtn = computed(() => ['student', 'admin'].includes(props.role))

</script>

<template>
    <Card class="bg-slate-700 dark:bg-surface-600 mt-5 relative">
        <template #content>
            <div class="overlay-img">
                <img src="assets/img/bg/shape-04.png" alt="img" class="img-fluid absolute left-[40%] top-0">
                <img src="assets/img/bg/shape-01.png" alt="img" class="img-fluid absolute bottom-0 left-[60%]">
                <img src="assets/img/bg/shape-02.png" alt="img" class="img-fluid absolute right-20">
                <img src="assets/img/bg/shape-03.png" alt="img" class="img-fluid absolute bottom-0 left-[15%]">
            </div>
            <div class="flex xl:items-center xl:justify-between xl:flex-row flex-col">
                <div class="mb-3 xl:mb-0">
                    <div class="flex items-center flex-wrap mb-2">
                        <h1 class="text-white mb-1">Welcome Back, {{ user.name }}</h1>

                        <Button v-if="showEditBtn" icon="pi pi-pencil" severity="secondary" class="ml-4" />
                    </div>
                    <p class="text-white">Have a Good day at work</p>
                </div>
            </div>
        </template>
    </Card>
    <!-- ==================== CARD WRAPPER ==================== -->

</template>

<style scoped lang="postcss">
/* --------------------------------------------------------------
   Tailwind utilities + PrimeVue overrides (already in app.css)
   -------------------------------------------------------------- */
.student-card-bg img {
    @apply absolute opacity-20 w-32 h-32 object-contain;
}

.student-card-bg img:nth-child(1) {
    top: 0;
    right: 0;
}

.student-card-bg img:nth-child(2) {
    top: 2rem;
    right: 2rem;
}

.student-card-bg img:nth-child(3) {
    bottom: 2rem;
    left: 2rem;
}

.student-card-bg img:nth-child(4) {
    bottom: 0;
    left: 0;
}

.truncate {
    @apply overflow-hidden text-ellipsis whitespace-nowrap;
}
</style>