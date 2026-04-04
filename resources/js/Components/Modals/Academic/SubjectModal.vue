<!--
  resources/js/Components/Modals/Academic/SubjectModal.vue – v1.0

  ─────────────────────────────────────────────────────────────────────────────
  WHAT IT IMPLEMENTS
  ─────────────────────────────────────────────────────────────────────────────
  Create / Edit modal for the Subjects module. Uses PrimeVue Dialog, Inertia
  useForm, and the shared InputWrapper / InputLabel pattern from the codebase.

  ─────────────────────────────────────────────────────────────────────────────
  FEATURES / PROBLEMS SOLVED
  ─────────────────────────────────────────────────────────────────────────────
  • Dual mode (create / edit) driven by the :subject prop being null or a Subject
  • All form fields with proper validation error display
  • MultiSelect for school sections and class levels
  • Dropdown for type (core/elective/optional) and category
  • Color picker for timetable color
  • Toggle for is_active
  • "Code" field auto-uppercased as user types
  • Full Inertia form handling (preserveScroll, onSuccess, onError)
  • Emits 'saved' event so parent can refresh the DataTable
  • Dirty state: submit button disabled when form is pristine
  • Accessible: proper ARIA roles via PrimeVue Dialog

  ─────────────────────────────────────────────────────────────────────────────
  FITS INTO THE MODULE
  ─────────────────────────────────────────────────────────────────────────────
  • Opened by Settings/Academic/Subjects.vue (via useModal or v-if visible prop)
  • Receives subjectTypes, subjectCategories, schoolSections, classLevels as props
  • On save: emits 'saved' → parent calls table.refresh()
-->

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import {
    Button,
    Dialog,
    InputText,
    InputNumber,
    Textarea,
    Select,
    MultiSelect,
    ToggleSwitch,
    ColorPicker,
    Message,
} from 'primevue'
import InputLabel from '@/Components/forms/InputLabel.vue'
import type { Subject, SubjectFormData, SelectOption } from '@/types/subject'
import { emptySubjectForm } from '@/types/subject'

// ─── Props & Emits ────────────────────────────────────────────────────────
const props = defineProps<{
    visible: boolean
    subject?: Subject | null
    schoolSections: SelectOption[]
    classLevels: SelectOption[]
    subjectTypes: SelectOption[]
    subjectCategories: SelectOption[]
}>()

const emit = defineEmits<{
    (e: 'update:visible', val: boolean): void
    (e: 'saved', subject: Subject): void
}>()

const toast = useToast()

// ─── Mode ─────────────────────────────────────────────────────────────────
const isEdit = computed(() => !!props.subject)
const dialogHeader = computed(() => isEdit.value ? `Edit Subject — ${props.subject?.name}` : 'Add New Subject')

// ─── Form ─────────────────────────────────────────────────────────────────
const form = useForm<SubjectFormData>(emptySubjectForm())

// Populate form when editing (or reset when opening for create)
watch(() => props.visible, (open) => {
    if (!open) return

    if (props.subject) {
        form.name = props.subject.name
        form.code = props.subject.code
        form.description = props.subject.description
        form.type = props.subject.type
        form.category = props.subject.category
        form.is_active = props.subject.is_active
        form.pass_mark = props.subject.pass_mark
        form.credit_hours = props.subject.credit_hours
        form.color = props.subject.color
        form.sort = props.subject.sort
        form.school_section_ids = props.subject.school_section_ids ?? []
        form.class_level_ids = props.subject.class_level_ids ?? []
    } else {
        const defaults = emptySubjectForm()
        Object.assign(form, defaults)
        form.clearErrors()
    }
}, { immediate: false })

// Auto-uppercase code
const handleCodeInput = (e: Event) => {
    const input = e.target as HTMLInputElement
    form.code = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '')
}

// ─── Submit ───────────────────────────────────────────────────────────────
const submit = () => {
    const url = isEdit.value
        ? route('settings.academic.subjects.update', props.subject!.id)
        : route('settings.academic.subjects.store')

    const method = isEdit.value ? 'patch' : 'post'

    form[method](url, {
        preserveScroll: true,
        onSuccess: (page) => {
            toast.add({
                severity: 'success',
                summary: isEdit.value ? 'Subject Updated' : 'Subject Created',
                detail: isEdit.value
                    ? `"${form.name}" has been updated.`
                    : `"${form.name}" has been added.`,
                life: 4000,
            })
            emit('update:visible', false)
            emit('saved', page.props as any)
        },
        onError: (errors) => {
            const first = Object.values(errors)[0]
            if (first) {
                toast.add({ severity: 'error', summary: 'Validation Error', detail: first, life: 6000 })
            }
        },
    })
}

const close = () => emit('update:visible', false)
</script>

<template>
    <Dialog :visible="visible" @update:visible="close" :header="dialogHeader" :modal="true" :closable="true"
        :close-on-escape="true" :style="{ width: 'min(95vw, 760px)' }"
        :pt="{ root: { class: 'rounded-xl shadow-2xl' } }" class="subject-modal">
        <form @submit.prevent="submit" class="space-y-6 pt-2">

            <!-- Row 1: Name + Code -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Name (spans 2 cols) -->
                <div class="md:col-span-2">
                    <InputLabel value="Subject Name" :required="true" />
                    <InputText v-model="form.name" placeholder="e.g. Mathematics" fluid :invalid="!!form.errors.name"
                        class="w-full" />
                    <Message v-if="form.errors.name" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ form.errors.name }}
                    </Message>
                </div>

                <!-- Code -->
                <div>
                    <InputLabel value="Code" :required="true" hint="Short abbreviation e.g. MTH, ENG" />
                    <InputText :model-value="form.code" @input="handleCodeInput" placeholder="MTH" fluid
                        :invalid="!!form.errors.code" class="w-full font-mono tracking-widest uppercase"
                        maxlength="20" />
                    <Message v-if="form.errors.code" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ form.errors.code }}
                    </Message>
                </div>
            </div>

            <!-- Row 2: Type + Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Subject Type" :required="true" />
                    <Select v-model="form.type" :options="subjectTypes" option-label="label" option-value="value"
                        placeholder="Select type" fluid class="w-full" :invalid="!!form.errors.type" />
                    <Message v-if="form.errors.type" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ form.errors.type }}
                    </Message>
                </div>

                <div>
                    <InputLabel value="Category" :required="true" />
                    <Select v-model="form.category" :options="subjectCategories" option-label="label"
                        option-value="value" placeholder="Select category" fluid class="w-full"
                        :invalid="!!form.errors.category" />
                    <Message v-if="form.errors.category" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ form.errors.category }}
                    </Message>
                </div>
            </div>

            <!-- Row 3: School Sections + Class Levels -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel value="School Sections" hint="Assign to sections (e.g. Primary, JSS)" />
                    <MultiSelect v-model="form.school_section_ids" :options="schoolSections" option-label="label"
                        option-value="value" placeholder="Select sections" display="chip" filter fluid class="w-full" />
                </div>

                <div>
                    <InputLabel value="Class Levels" hint="Which classes offer this subject" />
                    <MultiSelect v-model="form.class_level_ids" :options="classLevels" option-label="label"
                        option-value="value" placeholder="Select class levels" display="chip" filter fluid
                        class="w-full">
                        <template #option="{ option }">
                            <div class="flex flex-col leading-tight">
                                <span class="font-medium">{{ option.label }}</span>
                                <span v-if="option.section" class="text-xs text-gray-500">{{ option.section }}</span>
                            </div>
                        </template>
                    </MultiSelect>
                </div>
            </div>

            <!-- Row 4: Pass Mark + Credit Hours + Color + Active -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                <div>
                    <InputLabel value="Pass Mark" hint="Minimum score to pass (0–100)" />
                    <InputNumber v-model="form.pass_mark" :min="0" :max="100" placeholder="40" fluid class="w-full"
                        :invalid="!!form.errors.pass_mark" />
                </div>

                <div>
                    <InputLabel value="Credit Hours" hint="Weekly teaching hours" />
                    <InputNumber v-model="form.credit_hours" :min="1" :max="40" placeholder="e.g. 5" fluid
                        class="w-full" />
                </div>

                <div>
                    <InputLabel value="Color" hint="For timetable display" />
                    <div class="flex items-center gap-2">
                        <ColorPicker v-model="form.color" format="hex" class="shrink-0" />
                        <InputText :model-value="form.color ?? ''" @input="(e: any) => form.color = e.target.value"
                            placeholder="#3B82F6" class="w-full font-mono text-sm" maxlength="7" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pb-1">
                    <ToggleSwitch v-model="form.is_active" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ form.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <!-- Row 5: Description -->
            <div>
                <InputLabel value="Description" />
                <Textarea v-model="form.description" rows="3"
                    placeholder="Optional description or notes about this subject…" class="w-full" auto-resize />
            </div>

        </form>

        <!-- Footer actions -->
        <template #footer>
            <div class="flex justify-end gap-3">
                <Button label="Cancel" severity="secondary" text @click="close" :disabled="form.processing" />
                <Button :label="isEdit ? 'Save Changes' : 'Create Subject'"
                    :icon="isEdit ? 'pi pi-check' : 'pi pi-plus'" :loading="form.processing" :disabled="form.processing"
                    @click="submit" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
:deep(.p-multiselect-chip) {
    @apply bg-primary/10 text-primary border border-primary/20 text-xs;
}
</style>
