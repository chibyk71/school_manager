<script setup lang="ts">
/**
 * SubjectAssignmentsTab.vue
 *
 * Tab content rendered inside ClassSectionFormModal when viewing an existing
 * class section. Manages all teacher-subject assignments for the section.
 *
 * ── What This Component Does ──────────────────────────────────────────────────
 * 1. Lists all existing teacher-subject assignments for the section in a
 *    clean table with teacher name, subject, role, and actions
 * 2. Provides an inline "Assign Teacher to Subject" form (not a nested modal —
 *    keeping the UX flat since we're already inside a modal)
 * 3. Supports changing the role on an existing assignment inline
 * 4. Soft-removes assignments with a confirmation step
 *
 * ── Props ─────────────────────────────────────────────────────────────────────
 * section    ClassSection — the section whose assignments are being managed
 *            Must have teacher_subject_assignments loaded (detail response)
 *
 * ── Emits ─────────────────────────────────────────────────────────────────────
 * updated    fired after any assignment change so the parent can refresh
 *            the section data if needed
 *
 * ── API Calls ─────────────────────────────────────────────────────────────────
 * POST   /settings/academic/class-sections/{id}/subjects
 * PATCH  /settings/academic/class-sections/{id}/subjects/{assignmentId}
 * DELETE /settings/academic/class-sections/{id}/subjects/{assignmentId}
 *
 * ── Fits Into The Module ──────────────────────────────────────────────────────
 * Rendered as the "Subject Assignments" tab inside ClassSectionFormModal.
 * The parent modal passes the full section object (with assignments pre-loaded)
 * and handles refreshing after this component emits 'updated'.
 */

import { ref, computed } from 'vue'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import {
    Button,
    Select,
    DataTable,
    Column,
    Tag,
    ProgressSpinner,
} from 'primevue'
import type { ClassSection, TeacherSubjectAssignment, AssignmentRoleValue } from '@/types/class-section'
import { ASSIGNMENT_ROLES as ROLES } from '@/types/class-section'
import AsyncSelect from '@/Components/forms/AsyncSelect.vue'

// ── Props & emits ─────────────────────────────────────────────────────────────
const props = defineProps<{
    section: ClassSection
}>()

const emit = defineEmits<{
    updated: []
}>()

const toast = useToast()
const confirm = useConfirm()

// ── Local assignments list (starts from prop, mutated optimistically) ─────────
const assignments = ref<TeacherSubjectAssignment[]>(
    props.section.teacher_subject_assignments ?? []
)

// ── Assign form state ─────────────────────────────────────────────────────────
const showAssignForm = ref(false)
const ASSIGNMENT_ROLES = ROLES.map(r => ({ label: r.label, value: r.value }))
const assignLoading = ref(false)
const assignForm = ref({
    teacher_id: null as string | null,
    subject_id: null as string | null,
    role: null as AssignmentRoleValue | null,
})
const assignErrors = ref<Record<string, string>>({})

// ── Role editing state ────────────────────────────────────────────────────────
// Maps assignment id → new role value (for inline editing)
const editingRole = ref<Record<number, string | null>>({})
const savingRole = ref<Record<number, boolean>>({})

// ── Computed ──────────────────────────────────────────────────────────────────
const baseUrl = computed(() =>
    route('settings.academic.class-sections.subjects.assign', props.section.id)
)

const assignmentUrl = (assignmentId: number) =>
    route('settings.academic.class-sections.subjects.update-role', {
        classSection: props.section.id,
        assignment: assignmentId,
    })

// ── Assign a new teacher-subject ─────────────────────────────────────────────
const submitAssign = async () => {
    if (!assignForm.value.teacher_id || !assignForm.value.subject_id) {
        assignErrors.value = {
            teacher_id: !assignForm.value.teacher_id ? 'Teacher is required.' : '',
            subject_id: !assignForm.value.subject_id ? 'Subject is required.' : '',
        }
        return
    }

    assignLoading.value = true
    assignErrors.value = {}

    try {
        const { data } = await axios.post(baseUrl.value, {
            teacher_id: assignForm.value.teacher_id,
            subject_id: assignForm.value.subject_id,
            role: assignForm.value.role,
        })

        // Optimistically add to list
        assignments.value.push(data.data)

        toast.add({
            severity: 'success',
            summary: 'Assigned',
            detail: 'Subject assignment created.',
            life: 3000,
        })

        // Reset form
        assignForm.value = { teacher_id: null, subject_id: null, role: null }
        showAssignForm.value = false
        emit('updated')
    } catch (err: any) {
        const errors = err.response?.data?.errors ?? {}
        const message = err.response?.data?.message ?? 'Assignment failed.'

        if (Object.keys(errors).length) {
            assignErrors.value = Object.fromEntries(
                Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
            )
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: message, life: 5000 })
        }
    } finally {
        assignLoading.value = false
    }
}

// ── Save a role change inline ─────────────────────────────────────────────────
const saveRoleChange = async (assignment: TeacherSubjectAssignment) => {
    const newRole = editingRole.value[assignment.id] ?? null
    savingRole.value[assignment.id] = true

    try {
        await axios.patch(assignmentUrl(assignment.id), { role: newRole })

        // Update local list
        const idx = assignments.value.findIndex(a => a.id === assignment.id)
        if (idx !== -1) {
            assignments.value[idx] = {
                ...assignments.value[idx],
                role: newRole,
                role_label: ASSIGNMENT_ROLES.find(r => r.value === newRole)?.label
                    ?? newRole ?? 'Subject Teacher',
            }
        }

        delete editingRole.value[assignment.id]
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Role updated.', life: 2500 })
        emit('updated')
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not update role.', life: 4000 })
    } finally {
        savingRole.value[assignment.id] = false
    }
}

// ── Remove an assignment ──────────────────────────────────────────────────────
const removeAssignment = (assignment: TeacherSubjectAssignment) => {
    confirm.require({
        message: `Remove ${assignment.teacher?.full_name ?? 'this teacher'} from ${assignment.subject?.name ?? 'this subject'}?`,
        header: 'Remove Assignment',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        accept: async () => {
            try {
                await axios.delete(assignmentUrl(assignment.id))
                assignments.value = assignments.value.filter(a => a.id !== assignment.id)
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Assignment removed.', life: 3000 })
                emit('updated')
            } catch {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Could not remove assignment.', life: 4000 })
            }
        },
    })
}

// ── Role tag severity mapping ─────────────────────────────────────────────────
const roleSeverity = (role: string | null): 'success' | 'info' | 'warn' | 'secondary' => {
    switch (role) {
        case 'subject_teacher': return 'success'
        case 'co_teacher': return 'info'
        case 'cover_teacher': return 'warn'
        default: return 'secondary'
    }
}
</script>

<template>
    <div class="space-y-5">

        <!-- Header row ────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ assignments.length }} subject assignment{{ assignments.length !== 1 ? 's' : '' }}
            </p>
            <Button label="Assign Teacher" icon="pi pi-plus" size="small" @click="showAssignForm = !showAssignForm"
                :outlined="showAssignForm" />
        </div>

        <!-- Assign form (collapsible) ─────────────────────────────────────── -->
        <Transition enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition-all duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-2">
            <div v-if="showAssignForm"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    New Assignment
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Teacher -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Teacher <span class="text-red-500">*</span>
                        </label>
                        <AsyncSelect id="assign-teacher" v-model="assignForm.teacher_id" :field="{
                            placeholder: 'Search teacher...',
                            search_url: route('options.staff'),
                        }" :invalid="!!assignErrors.teacher_id" />
                        <p v-if="assignErrors.teacher_id" class="text-xs text-red-500 mt-1">
                            {{ assignErrors.teacher_id }}
                        </p>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <AsyncSelect id="assign-subject" v-model="assignForm.subject_id" :field="{
                            placeholder: 'Search subject...',
                            search_url: route('options.subjects'),
                        }" :invalid="!!assignErrors.subject_id" />
                        <p v-if="assignErrors.subject_id" class="text-xs text-red-500 mt-1">
                            {{ assignErrors.subject_id }}
                        </p>
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Role
                        </label>
                        <Select v-model="assignForm.role" :options="ASSIGNMENT_ROLES" option-label="label"
                            option-value="value" placeholder="Subject Teacher (default)" show-clear class="w-full" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <Button label="Cancel" severity="secondary" text size="small" @click="showAssignForm = false" />
                    <Button label="Assign" icon="pi pi-check" size="small" :loading="assignLoading"
                        @click="submitAssign" />
                </div>
            </div>
        </Transition>

        <!-- Empty state ────────────────────────────────────────────────────── -->
        <div v-if="assignments.length === 0"
            class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
            <i class="pi pi-book text-5xl mb-3 opacity-30" />
            <p class="text-sm">No subject assignments yet.</p>
            <p class="text-xs mt-1">Use the button above to assign teachers to subjects.</p>
        </div>

        <!-- Assignments table ──────────────────────────────────────────────── -->
        <DataTable v-else :value="assignments" size="small" class="text-sm" striped-rows>
            <!-- Teacher -->
            <Column header="Teacher" field="teacher">
                <template #body="{ data }">
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ data.teacher?.full_name ?? '—' }}
                    </span>
                </template>
            </Column>

            <!-- Subject -->
            <Column header="Subject" field="subject">
                <template #body="{ data }">
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ data.subject?.name ?? '—' }}
                        <span v-if="data.subject?.code" class="text-gray-400 text-xs ml-1">
                            ({{ data.subject.code }})
                        </span>
                    </span>
                </template>
            </Column>

            <!-- Role — inline editable ───────────────────────────────────── -->
            <Column header="Role" style="width: 200px">
                <template #body="{ data }">
                    <!-- Editing mode -->
                    <div v-if="data.id in editingRole" class="flex items-center gap-1.5">
                        <Select v-model="editingRole[data.id]" :options="ASSIGNMENT_ROLES" option-label="label"
                            option-value="value" placeholder="Default" show-clear class="text-xs"
                            style="min-width: 130px" />
                        <Button icon="pi pi-check" text rounded size="small" severity="success"
                            :loading="savingRole[data.id]" @click="saveRoleChange(data)" />
                        <Button icon="pi pi-times" text rounded size="small" severity="secondary"
                            @click="delete editingRole[data.id]" />
                    </div>

                    <!-- Display mode -->
                    <div v-else class="flex items-center gap-2">
                        <Tag :value="data.role_label" :severity="roleSeverity(data.role)" class="text-xs" />
                        <Button icon="pi pi-pencil" text rounded size="small" severity="secondary"
                            class="opacity-0 group-hover:opacity-100" v-tooltip.top="'Change role'"
                            @click="editingRole[data.id] = data.role" />
                    </div>
                </template>
            </Column>

            <!-- Actions -->
            <Column header="" style="width: 48px" body-class="text-center">
                <template #body="{ data }">
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small"
                        v-tooltip.top="'Remove assignment'" @click="removeAssignment(data)" />
                </template>
            </Column>
        </DataTable>

    </div>
</template>
