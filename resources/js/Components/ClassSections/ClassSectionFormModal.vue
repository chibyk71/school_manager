<script setup lang="ts">
/**
 * ClassSectionFormModal.vue
 *
 * Create / Edit modal for a single ClassSection.
 * Registered in ModalDirectory.ts as 'class-section-form'.
 *
 * ── Pattern ───────────────────────────────────────────────────────────────────
 * Follows the exact SectionFormModal pattern already in the codebase:
 *   - Receives payload via ModalService (section? for edit, mode for create)
 *   - Uses useModalForm composable for Inertia submission + toast
 *   - Emits 'close' to ModalService on cancel or successful save
 *   - Two tabs: "Details" (always) + "Subject Assignments" (edit mode only)
 *   - Dirty-state guard: warns user before closing with unsaved changes
 *   - Modal is marked persistent in ModalDirectory to prevent accidental ESC close
 *
 * ── Tabs ──────────────────────────────────────────────────────────────────────
 * Tab 1 — Details
 *   name, display_name (with "restore default" link), room, capacity,
 *   form_teacher (AsyncSelect), status toggle, sort_order
 *
 * Tab 2 — Subject Assignments  (edit mode only)
 *   Renders SubjectAssignmentsTab.vue which manages its own API calls
 *
 * ── Props (via ModalService payload) ─────────────────────────────────────────
 * section?    ClassSection   — present in edit mode, absent in create mode
 * classLevelId?  string      — pre-selected class level (passed from index filter)
 *
 * ── API ───────────────────────────────────────────────────────────────────────
 * Create: POST   /settings/academic/class-sections
 * Update: PATCH  /settings/academic/class-sections/{id}
 */

import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import {
    Button,
    InputText,
    InputNumber,
    Select,
    ToggleSwitch,
    Tabs,
    TabList,
    Tab,
    TabPanels,
    TabPanel,
    Message,
} from 'primevue'
import { useModal } from '@/composables/useModal'
import AsyncSelect from '@/Components/forms/AsyncSelect.vue'
import InputLabel from '@/Components/forms/InputLabel.vue'
import SubjectAssignmentsTab from '@/Components/ClassSections/SubjectAssignmentsTab.vue'
import ClassSectionStatusBadge from '@/Components/ClassSections/ClassSectionStatusBadge.vue'
import type { ClassSection, ClassSectionFormData } from '@/types/class-section'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps<{
    section?: ClassSection | null
    classLevelId?: string | null
}>()

const emit = defineEmits<{ close: [] }>()
const modal = useModal()
const toast = useToast()

// ── Mode detection ────────────────────────────────────────────────────────────
const isEditMode = computed(() => !!props.section)
const title = computed(() => isEditMode.value
    ? `Edit — ${props.section!.display_name}`
    : 'Create Class Section'
)

// ── Form state ────────────────────────────────────────────────────────────────
const form = ref<ClassSectionFormData & { status: 'active' | 'inactive' }>({
    class_level_id: props.classLevelId ?? props.section?.class_level_id ?? '',
    name: props.section?.name ?? '',
    display_name: props.section?.display_name_stored ?? null,
    room: props.section?.room ?? null,
    capacity: props.section?.capacity ?? 0,
    form_teacher_id: props.section?.form_teacher_id ?? null,
    sort_order: props.section?.sort_order ?? null,
    status: (props.section?.status as 'active' | 'inactive') ?? 'active',
})

const errors = ref<Record<string, string>>({})
const loading = ref(false)
const isDirty = ref(false)
const activeTab = ref(0)

// Track dirty state
watch(form, () => { isDirty.value = true }, { deep: true })

// ── Display name helpers ──────────────────────────────────────────────────────
/**
 * Whether the display_name field has been manually customised.
 * If null → auto-computed by backend; show a "using auto name" hint.
 */
const hasCustomDisplayName = computed(() => form.value.display_name !== null)

const restoreAutoDisplayName = () => {
    form.value.display_name = null
    isDirty.value = true
}

// ── Section detail refresh after subject assignment changes ───────────────────
const sectionData = ref<ClassSection | null>(props.section ?? null)

const refreshSection = async () => {
    if (!props.section?.id) return
    try {
        // Reload just this section with subject assignments
        const response = await (await import('axios')).default.get(
            route('settings.academic.class-sections.update', props.section.id),
            { params: { with: 'teacherSubjectAssignments.teacher,teacherSubjectAssignments.subject' } }
        )
        sectionData.value = response.data.data
    } catch {
        // Non-critical — tab will just show stale data until modal is reopened
    }
}

// ── Submit ────────────────────────────────────────────────────────────────────
const submit = async () => {
    errors.value = {}
    loading.value = true

    const url = isEditMode.value
        ? route('settings.academic.class-sections.update', props.section!.id)
        : route('settings.academic.class-sections.store')

    const method = isEditMode.value ? 'patch' : 'post'

    // Build payload — strip null sort_order and null display_name to let
    // backend apply defaults rather than overwriting with null
    const payload: Record<string, any> = {
        class_level_id: form.value.class_level_id,
        name: form.value.name,
        room: form.value.room || null,
        capacity: form.value.capacity ?? 0,
        form_teacher_id: form.value.form_teacher_id || null,
        status: form.value.status,
    }

    if (form.value.display_name !== null) {
        payload.display_name = form.value.display_name
    }
    if (form.value.sort_order !== null && form.value.sort_order !== undefined) {
        payload.sort_order = form.value.sort_order
    }

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditMode.value
                    ? `Section "${form.value.name}" updated.`
                    : `Section "${form.value.name}" created.`,
                life: 4000,
            })
            isDirty.value = false
            emit('close')
        },
        onError: (errs) => {
            errors.value = errs as Record<string, string>
            // Switch to Details tab if there are field errors there
            if (Object.keys(errs).some(k => ['name', 'display_name', 'room', 'capacity', 'form_teacher_id'].includes(k))) {
                activeTab.value = 0
            }
            toast.add({
                severity: 'error',
                summary: 'Validation Failed',
                detail: Object.values(errs)[0] as string,
                life: 5000,
            })
        },
        onFinish: () => { loading.value = false },
    })
}

// ── Close guard ───────────────────────────────────────────────────────────────
const requestClose = () => {
    if (!isDirty.value) {
        emit('close')
        return
    }
    // Inline confirm — keeping UX within the modal rather than nesting dialogs
    if (window.confirm('You have unsaved changes. Discard them?')) {
        emit('close')
    }
}
</script>

<template>
    <!-- The Dialog shell is provided by ResourceDialog.vue (ModalService).   -->
    <!-- This component renders the modal BODY only.                           -->
    <div class="flex flex-col gap-0 -mx-6 -mb-6">

        <!-- Tabs ────────────────────────────────────────────────────────── -->
        <Tabs v-model:value="activeTab">
            <TabList class="px-6 border-b border-gray-200 dark:border-gray-700">
                <Tab :value="0">
                    <span class="flex items-center gap-2 text-sm">
                        <i class="pi pi-pen-to-square" />
                        Details
                    </span>
                </Tab>
                <Tab :value="1" :disabled="!isEditMode">
                    <span class="flex items-center gap-2 text-sm">
                        <i class="pi pi-book" />
                        Subject Assignments
                        <span v-if="(sectionData?.teacher_subject_assignments_count ?? 0) > 0" class="inline-flex items-center justify-center w-5 h-5 rounded-full
                                   bg-primary-100 text-primary-700 dark:bg-primary-900/40
                                   dark:text-primary-300 text-xs font-semibold">
                            {{ sectionData?.teacher_subject_assignments_count }}
                        </span>
                    </span>
                </Tab>
            </TabList>

            <TabPanels>

                <!-- ── Tab 1: Details ─────────────────────────────────── -->
                <TabPanel :value="0">
                    <div class="p-6 space-y-5">

                        <!-- Section name + status badge (edit mode header) -->
                        <div v-if="isEditMode" class="flex items-center gap-3 mb-1">
                            <ClassSectionStatusBadge :section="section!" show-capacity size="md" />
                        </div>

                        <!-- Class level selector (create mode only) ──────── -->
                        <div v-if="!isEditMode">
                            <InputLabel value="Class Level" :required="true" />
                            <AsyncSelect id="class-level" v-model="form.class_level_id" :field="{
                                placeholder: 'Select a class level...',
                                search_url: route('settings.academic.class-levels.options'),
                            }" :invalid="!!errors.class_level_id" />
                            <Message v-if="errors.class_level_id" severity="error" variant="simple" class="mt-1">
                                {{ errors.class_level_id }}
                            </Message>
                        </div>

                        <!-- Name ────────────────────────────────────────── -->
                        <div>
                            <InputLabel value="Arm / Section Name" :required="true"
                                hint="The arm label — e.g. A, B, Diamond, Gold. Short identifiers work best." />
                            <InputText v-model="form.name" placeholder="e.g. A, B, Diamond" :invalid="!!errors.name"
                                fluid class="uppercase" />
                            <Message v-if="errors.name" severity="error" variant="simple" class="mt-1">
                                {{ errors.name }}
                            </Message>
                        </div>

                        <!-- Display name ─────────────────────────────────── -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <InputLabel value="Display Name"
                                    hint="Full label shown to users, e.g. JSS 1A. Leave blank to auto-generate from the class level name + arm." />
                                <button v-if="hasCustomDisplayName" type="button"
                                    class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                    @click="restoreAutoDisplayName">
                                    Restore auto-name
                                </button>
                            </div>
                            <InputText v-model="form.display_name" :placeholder="form.name
                                ? `Auto: ${section?.class_level?.name ?? '...'} ${form.name}`
                                : 'Auto-generated from class level + arm'" :invalid="!!errors.display_name"
                                fluid />
                            <p v-if="!hasCustomDisplayName" class="text-xs text-gray-400 mt-1">
                                Will be auto-generated when saved.
                            </p>
                            <Message v-if="errors.display_name" severity="error" variant="simple" class="mt-1">
                                {{ errors.display_name }}
                            </Message>
                        </div>

                        <!-- Room + Capacity (side by side) ──────────────── -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Room" hint="Physical room reference, e.g. Block A Room 3." />
                                <InputText v-model="form.room" placeholder="e.g. Block A Room 3"
                                    :invalid="!!errors.room" fluid />
                                <Message v-if="errors.room" severity="error" variant="simple" class="mt-1">
                                    {{ errors.room }}
                                </Message>
                            </div>

                            <div>
                                <InputLabel value="Capacity" hint="Maximum students allowed. Set to 0 for no limit." />
                                <InputNumber v-model="form.capacity" :min="0" :max="1000" :invalid="!!errors.capacity"
                                    fluid placeholder="0 = uncapped" :show-buttons="false" />
                                <Message v-if="errors.capacity" severity="error" variant="simple" class="mt-1">
                                    {{ errors.capacity }}
                                </Message>
                            </div>
                        </div>

                        <!-- Form teacher ─────────────────────────────────── -->
                        <div>
                            <InputLabel value="Form Teacher"
                                hint="The class teacher / form master responsible for this section." />
                            <AsyncSelect id="form-teacher" v-model="form.form_teacher_id" :field="{
                                placeholder: 'Search and select a staff member...',
                                search_url: route('options.staff'),
                                none_label: '— No form teacher —',
                            }" :invalid="!!errors.form_teacher_id" />
                            <Message v-if="errors.form_teacher_id" severity="error" variant="simple" class="mt-1">
                                {{ errors.form_teacher_id }}
                            </Message>
                        </div>

                        <!-- Sort order ────────────────────────────────────── -->
                        <div>
                            <InputLabel value="Sort Order"
                                hint="Controls display order within a class level. Lower values appear first. Auto-assigned if left blank." />
                            <InputNumber v-model="form.sort_order" :min="0" :max="9999" placeholder="Auto-assigned"
                                fluid />
                        </div>

                        <!-- Status toggle ────────────────────────────────── -->
                        <div class="flex items-center gap-3 pt-1">
                            <ToggleSwitch v-model="form.status" true-value="active" false-value="inactive" />
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ form.status === 'active' ? 'Active' : 'Inactive' }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ form.status === 'active'
                                        ? 'Section is available for enrollment and scheduling.'
                                        : 'Section is hidden from enrollment and scheduling.'
                                    }}
                                </p>
                            </div>
                        </div>

                    </div>
                </TabPanel>

                <!-- ── Tab 2: Subject Assignments ─────────────────────── -->
                <TabPanel :value="1">
                    <div class="p-6">
                        <SubjectAssignmentsTab v-if="sectionData" :section="sectionData" @updated="refreshSection" />
                        <p v-else class="text-sm text-gray-400 text-center py-8">
                            Save the section first to manage subject assignments.
                        </p>
                    </div>
                </TabPanel>

            </TabPanels>
        </Tabs>

        <!-- Footer ──────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t
                   border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <Button label="Cancel" severity="secondary" text :disabled="loading" @click="requestClose" />
            <Button :label="isEditMode ? 'Save Changes' : 'Create Section'" icon="pi pi-check" :loading="loading"
                @click="submit" />
        </div>

    </div>
</template>
