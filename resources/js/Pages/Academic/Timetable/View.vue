<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, Select, Tag } from 'primevue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TimetableGrid from '@/Components/Timetable/TimetableGrid.vue';
import {
    TIMETABLE_STATUS_CONFIG,
    normalizeTimetable,
    type BuilderClassSection,
    type RawTimetableResource,
    type TimetableDaySchedule,
    type TimetableSlot,
    type UUID,
} from '@/types/timetable';

const props = defineProps<{
    timetable: RawTimetableResource;
    slots: TimetableSlot[];
    classSections?: BuilderClassSection[];
    periodSchedules?: TimetableDaySchedule[];
    filter?: {
        class_section_id?: string | null;
        teacher_id?: string | number | null;
    };
}>();

const tt = computed(() => normalizeTimetable(props.timetable));

const selectedSectionId = ref<UUID | null>(
    (props.filter?.class_section_id as UUID | null) ?? null,
);

const teacherFilter = ref<string | number | null>(props.filter?.teacher_id ?? null);

const sectionOptions = computed(() =>
    (props.classSections ?? []).map((s) => ({
        label: s.display_name || s.name,
        value: s.id,
    })),
);

const teacherOptions = computed(() => {
    const map = new Map<string, string>();
    for (const s of props.slots ?? []) {
        if (s.teacher_id != null && s.teacher_name) {
            map.set(String(s.teacher_id), s.teacher_full_name || s.teacher_name);
        }
    }
    return Array.from(map.entries()).map(([value, label]) => ({ value, label }));
});

const filteredSlots = computed(() => {
    let list = props.slots ?? [];
    if (selectedSectionId.value) {
        list = list.filter(
            (s) => String(s.class_section_id) === String(selectedSectionId.value),
        );
    }
    if (teacherFilter.value != null && teacherFilter.value !== '') {
        list = list.filter((s) => String(s.teacher_id) === String(teacherFilter.value));
    }
    return list;
});

const statusCfg = computed(
    () => TIMETABLE_STATUS_CONFIG[tt.value.status] ?? TIMETABLE_STATUS_CONFIG.active,
);

const goBack = () => router.visit('/timetables');
</script>

<template>
    <AuthenticatedLayout
        :title="tt.title"
        :crumb="[
            { label: 'Dashboard', url: '/dashboard' },
            { label: 'Academic' },
            { label: 'Timetables', url: '/timetables' },
            { label: 'View' },
        ]"
        :buttons="[
            {
                label: 'Back',
                icon: 'ti ti-arrow-left',
                severity: 'secondary',
                onClick: goBack,
            },
        ]"
    >
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <Tag :value="statusCfg.label" :severity="statusCfg.severity" />
            <span class="text-sm text-muted-color">
                {{ tt.school_section_name }} · {{ tt.term_name }}
            </span>
            <span class="text-xs text-muted-color">
                {{ tt.effective_from }}
                <template v-if="tt.effective_to"> → {{ tt.effective_to }} </template>
            </span>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-wrap items-end gap-4 mb-4">
                    <div v-if="sectionOptions.length" class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-color">Class section</label>
                        <Select
                            v-model="selectedSectionId"
                            :options="sectionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All sections"
                            class="w-56"
                            show-clear
                        />
                    </div>
                    <div v-if="teacherOptions.length" class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-color">Teacher</label>
                        <Select
                            v-model="teacherFilter"
                            :options="[{ label: 'All teachers', value: null }, ...teacherOptions]"
                            option-label="label"
                            option-value="value"
                            placeholder="All teachers"
                            class="w-56"
                            show-clear
                        />
                    </div>
                </div>

                <TimetableGrid
                    :slots="filteredSlots"
                    :period-schedules="periodSchedules ?? tt.day_schedules ?? []"
                    :working-days="tt.working_days"
                    :read-only="true"
                />
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
