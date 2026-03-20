<script setup lang="ts">
/**
 * SectionFormModal.vue — Create / Edit School Section
 *
 * ── Responsibilities ─────────────────────────────────────────────────────
 * Handles both create and edit of a SchoolSection in a single modal.
 * Internally manages two views:
 *   'form'      — standard create/edit fields
 *   'templates' — grid of available templates to pick from
 *
 * The user reaches the templates view by clicking "Add from Templates"
 * inside the create form. Selecting a template populates the form fields
 * and switches back to 'form' view for review before saving.
 *
 * ── Props (via ModalDirectory payload) ───────────────────────────────────
 *   mode:    'create' | 'edit'
 *   section: SchoolSection | null   (null on create)
 *
 * ── Emits ────────────────────────────────────────────────────────────────
 * Fires 'saved' on the modal emitter (not Vue emit) so the parent
 * Index.vue can call refreshTable() via modal.open(...).on('saved', cb).
 *
 * ── Form Fields ──────────────────────────────────────────────────────────
 *   name          string  required  machine-safe slug (auto-generated from display_name)
 *   display_name  string  required
 *   short_code    string  required  max 10 chars, auto-uppercased
 *   description   string  optional
 *   is_active     boolean default true (create) | current value (edit)
 *   sort_order    hidden  — backend auto-assigns; not shown to user
 *
 * ── Template Flow ────────────────────────────────────────────────────────
 * 1. On mount (create mode) fetch GET /settings/school/sections/templates
 * 2. User clicks "Add from Templates" → view switches to 'templates'
 * 3. User clicks a template card → prefill form fields → view = 'form'
 * 4. User reviews prefilled fields → submits normally
 *
 * Templates endpoint returns { templates: SchoolSectionTemplate[], available_count }
 * where available = not yet created for this school.
 * Already-created templates are shown but disabled with a "Already added" badge.
 *
 * ── Submission ───────────────────────────────────────────────────────────
 * Uses Inertia useForm for validation error propagation and processing state.
 * POST  /settings/school/sections        (create)
 * PATCH /settings/school/sections/{id}   (edit)
 * On success: fires modal emitter 'saved' → closes modal.
 *
 * ── Dirty-state guard ────────────────────────────────────────────────────
 * If the user tries to close while the form is dirty (isDirty),
 * a PrimeVue confirm dialog asks for confirmation before closing.
 *
 * @see App\Http\Controllers\Settings\SchoolSectionController
 * @see resources/js/types/school-section.ts
 * @see resources/js/Pages/Settings/Sections/Index.vue
 */

import { ref, computed, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import {
    Button,
    InputText,
    Textarea,
    ToggleSwitch,
    Message,
    ProgressSpinner,
    Tag,
} from 'primevue'
import { useModal } from '@/composables/useModal'
import type {
    SchoolSection,
    SchoolSectionFormData,
    SchoolSectionTemplate,
    SchoolSectionTemplatesResponse,
} from '@/types/school-section'
import axios from 'axios'

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
    mode: 'create' | 'edit'
    section: SchoolSection | null
}>()

// ── Services ──────────────────────────────────────────────────────────────
const modal = useModal()
const toast = useToast()
const confirm = useConfirm()

// ── Internal view state ───────────────────────────────────────────────────
// 'form'      → standard create/edit fields
// 'templates' → template picker grid
type ModalView = 'form' | 'templates'
const currentView = ref<ModalView>('form')

// ── Template data ─────────────────────────────────────────────────────────
const templates = ref<SchoolSectionTemplate[]>([])
const templatesLoading = ref(false)
const templatesError = ref<string | null>(null)
const selectedTemplateKey = ref<string | null>(null)

// ── Inertia Form ──────────────────────────────────────────────────────────
const form = useForm<SchoolSectionFormData>({
    name: props.section?.name ?? '',
    display_name: props.section?.display_name ?? '',
    short_code: props.section?.short_code ?? '',
    description: props.section?.description ?? '',
    is_active: props.section?.is_active ?? true,
})

// ── Computed ──────────────────────────────────────────────────────────────

const isEdit = computed(() => props.mode === 'edit')
const modalTitle = computed(() =>
    currentView.value === 'templates'
        ? 'Add from Templates'
        : isEdit.value ? 'Edit Section' : 'New Section'
)

const submitLabel = computed(() =>
    form.processing
        ? 'Saving…'
        : isEdit.value ? 'Save Changes' : 'Create Section'
)

// ── Auto-generate `name` slug from display_name (create mode only) ────────
// The `name` field is the machine-safe identifier stored in the DB.
// We generate it automatically so the user never has to think about it.
// On edit, the name is locked — changing it would break external references.
watch(
    () => form.display_name,
    (val) => {
        if (isEdit.value) return  // never auto-change on edit
        form.name = val
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s_-]/g, '')   // strip special chars
            .replace(/\s+/g, '_')             // spaces → underscore
            .replace(/-+/g, '_')              // dashes → underscore
            .replace(/_+/g, '_')              // collapse multiple underscores
            .replace(/^_|_$/g, '')            // trim leading/trailing
    }
)

// Auto-uppercase short_code as user types
watch(
    () => form.short_code,
    (val) => {
        if (val !== val.toUpperCase()) {
            form.short_code = val.toUpperCase()
        }
    }
)

// ── Template fetch ────────────────────────────────────────────────────────
// Fetched once on mount (create mode) so the "Add from Templates" button
// is instantly responsive when clicked — no loading delay on click.
onMounted(() => {
    if (!isEdit.value) {
        fetchTemplates()
    }
})

async function fetchTemplates(): Promise<void> {
    templatesLoading.value = true
    templatesError.value = null

    try {
        const { data } = await axios.get<SchoolSectionTemplatesResponse>(
            route('settings.school.sections.templates')
        )
        templates.value = data.templates
    } catch (err: any) {
        templatesError.value =
            err.response?.data?.message ?? 'Failed to load templates.'
    } finally {
        templatesLoading.value = false
    }
}

// ── View switching ────────────────────────────────────────────────────────
function showTemplates(): void {
    currentView.value = 'templates'
}

function showForm(): void {
    currentView.value = 'form'
}

/**
 * User picked a template — prefill the form and switch back to form view.
 * Only available templates (not yet created for this school) can be selected.
 */
function selectTemplate(template: SchoolSectionTemplate): void {
    if (!template.available) return

    selectedTemplateKey.value = template.key

    form.name = template.key
    form.display_name = template.display_name
    form.short_code = template.short_code
    form.description = template.description ?? ''
    form.is_active = true

    // Clear any stale errors from a previous submission attempt
    form.clearErrors()

    currentView.value = 'form'

    toast.add({
        severity: 'info',
        summary: 'Template applied',
        detail: `"${template.display_name}" has been filled in. Review and save.`,
        life: 4000,
    })
}

// ── Form submission ───────────────────────────────────────────────────────
function submit(): void {
    const url = isEdit.value
        ? route('settings.school.sections.update', props.section!.id)
        : route('settings.school.sections.store')

    const method = isEdit.value ? 'patch' : 'post'

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEdit.value
                    ? `"${form.display_name}" updated.`
                    : `"${form.display_name}" created.`,
                life: 4000,
            })
            // Notify the parent page to refresh the DataTable
            modal.emitter.value?.emit('saved')
            modal.closeCurrent()
        },
        onError: () => {
            // Inertia populates form.errors — PrimeVue :invalid bindings handle display.
            // Show a summary toast so the user knows to look for red fields.
            toast.add({
                severity: 'error',
                summary: 'Validation failed',
                detail: 'Please correct the highlighted fields.',
                life: 5000,
            })
        },
    })
}

// ── Close / dirty-state guard ─────────────────────────────────────────────
function requestClose(): void {
    if (form.isDirty) {
        confirm.require({
            header: 'Discard changes?',
            message: 'You have unsaved changes. Close anyway?',
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Discard',
            rejectLabel: 'Keep editing',
            acceptClass: 'p-button-danger',
            rejectClass: 'p-button-secondary p-button-outlined',
            accept: () => modal.closeCurrent(),
        })
    } else {
        modal.closeCurrent()
    }
}
</script>

<template>
    <div class="flex flex-col gap-0 min-h-[420px]">

        <!-- ── Modal Header ───────────────────────────────────────────── -->
        <div class="flex items-center justify-between pb-4 mb-6
                    border-b border-surface-200 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <!-- Back arrow (templates view only) -->
                <Button v-if="currentView === 'templates'" icon="pi pi-arrow-left" text rounded severity="secondary"
                    size="small" aria-label="Back to form" @click="showForm" />
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">
                    {{ modalTitle }}
                </h2>
            </div>

            <Button icon="pi pi-times" text rounded severity="secondary" size="small" aria-label="Close"
                @click="requestClose" />
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             VIEW: FORM
             ══════════════════════════════════════════════════════════════ -->
        <template v-if="currentView === 'form'">
            <form class="flex flex-col gap-5" @submit.prevent="submit" novalidate>
                <!-- ── Display Name ────────────────────────────────────── -->
                <div class="field">
                    <label for="display_name" class="block text-sm font-medium mb-1.5">
                        Section Name
                        <span class="text-red-500 ml-0.5">*</span>
                    </label>
                    <InputText id="display_name" v-model="form.display_name" class="w-full"
                        :invalid="!!form.errors.display_name" placeholder="e.g. Junior Secondary School"
                        autocomplete="off" :disabled="form.processing" />
                    <small v-if="form.errors.display_name" class="text-red-500 text-xs mt-1 block">
                        {{ form.errors.display_name }}
                    </small>
                </div>

                <!-- ── Short Code + is_active row ─────────────────────── -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Short Code -->
                    <div class="field">
                        <label for="short_code" class="block text-sm font-medium mb-1.5">
                            Short Code
                            <span class="text-red-500 ml-0.5">*</span>
                        </label>
                        <InputText id="short_code" v-model="form.short_code" class="w-full font-mono uppercase"
                            :invalid="!!form.errors.short_code" placeholder="JSS" maxlength="10" autocomplete="off"
                            :disabled="form.processing" />
                        <small class="text-xs text-surface-500 mt-1 block">
                            Max 10 characters — auto-uppercased
                        </small>
                        <small v-if="form.errors.short_code" class="text-red-500 text-xs block">
                            {{ form.errors.short_code }}
                        </small>
                    </div>

                    <!-- Active toggle -->
                    <div class="field flex flex-col justify-start">
                        <label class="block text-sm font-medium mb-1.5">
                            Status
                        </label>
                        <div class="flex items-center gap-3 mt-1">
                            <ToggleSwitch v-model="form.is_active" :disabled="form.processing"
                                aria-label="Section active" />
                            <span class="text-sm">
                                {{ form.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <small v-if="form.errors.is_active" class="text-red-500 text-xs mt-1 block">
                            {{ form.errors.is_active }}
                        </small>
                    </div>
                </div>

                <!-- ── Description ────────────────────────────────────── -->
                <div class="field">
                    <label for="description" class="block text-sm font-medium mb-1.5">
                        Description
                        <span class="text-surface-400 font-normal text-xs ml-1">
                            (optional)
                        </span>
                    </label>
                    <Textarea id="description" v-model="form.description" class="w-full"
                        :invalid="!!form.errors.description" rows="3"
                        placeholder="Brief description of this academic section…" :disabled="form.processing"
                        auto-resize />
                    <small v-if="form.errors.description" class="text-red-500 text-xs mt-1 block">
                        {{ form.errors.description }}
                    </small>
                </div>

                <!-- ── Source hint (edit mode, template-sourced) ───────── -->
                <Message v-if="isEdit && section?.source === 'template'" severity="info" :closable="false"
                    class="text-sm">
                    This section was created from a template. Editing any field
                    will mark it as a custom section.
                </Message>

                <!-- ── Template-sourced hint (just filled from template) ── -->
                <Message v-if="!isEdit && selectedTemplateKey" severity="info" :closable="false" class="text-sm">
                    Fields pre-filled from the
                    <strong>{{ form.display_name }}</strong> template.
                    You can edit them before saving.
                </Message>

                <!-- ── Form Actions ───────────────────────────────────── -->
                <div class="flex items-center justify-between pt-2
                            border-t border-surface-200 dark:border-surface-700 mt-2">

                    <!-- Add from Templates link (create mode only) -->
                    <Button v-if="!isEdit" label="Add from Templates" icon="pi pi-th-large" text severity="secondary"
                        size="small" type="button" :disabled="form.processing" @click="showTemplates" />
                    <span v-else />

                    <!-- Submit + Cancel -->
                    <div class="flex items-center gap-3">
                        <Button label="Cancel" text severity="secondary" type="button" :disabled="form.processing"
                            @click="requestClose" />
                        <Button :label="submitLabel" icon="pi pi-check" type="submit" :loading="form.processing"
                            :disabled="form.processing" />
                    </div>
                </div>
            </form>
        </template>

        <!-- ══════════════════════════════════════════════════════════════
             VIEW: TEMPLATES
             ══════════════════════════════════════════════════════════════ -->
        <template v-else-if="currentView === 'templates'">

            <!-- Loading state -->
            <div v-if="templatesLoading" class="flex flex-col items-center justify-center flex-1 py-16">
                <ProgressSpinner style="width: 40px; height: 40px" stroke-width="4" />
                <p class="text-surface-500 text-sm mt-4">Loading templates…</p>
            </div>

            <!-- Error state -->
            <Message v-else-if="templatesError" severity="error" :closable="false">
                {{ templatesError }}
                <Button label="Retry" icon="pi pi-refresh" text severity="danger" size="small" class="ml-3"
                    @click="fetchTemplates" />
            </Message>

            <!-- Template grid -->
            <template v-else>
                <p class="text-sm text-surface-500 mb-4">
                    Select a preset template to pre-fill the form.
                    Templates already added to your school are disabled.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <button v-for="tmpl in templates" :key="tmpl.key" type="button" class="relative flex flex-col items-start gap-1 p-4 rounded-xl
                               border text-left transition-all duration-150
                               focus:outline-none focus-visible:ring-2
                               focus-visible:ring-primary focus-visible:ring-offset-2" :class="[
                                tmpl.available
                                    ? 'border-surface-200 dark:border-surface-700 '
                                    + 'bg-white dark:bg-surface-800 '
                                    + 'hover:border-primary hover:shadow-md cursor-pointer'
                                    : 'border-surface-100 dark:border-surface-800 '
                                    + 'bg-surface-50 dark:bg-surface-900 '
                                    + 'opacity-60 cursor-not-allowed',
                            ]" :disabled="!tmpl.available" :aria-disabled="!tmpl.available" @click="selectTemplate(tmpl)">
                        <!-- Already added badge -->
                        <Tag v-if="!tmpl.available" value="Already added" severity="secondary"
                            class="absolute top-2 right-2 text-xs" rounded />

                        <!-- Short code chip -->
                        <span class="inline-flex items-center px-2 py-0.5 rounded
                                   bg-primary/10 text-primary text-xs font-mono font-medium">
                            {{ tmpl.short_code }}
                        </span>

                        <!-- Name -->
                        <span class="text-sm font-medium text-surface-900 dark:text-surface-50 mt-1">
                            {{ tmpl.display_name }}
                        </span>

                        <!-- Description -->
                        <span v-if="tmpl.description" class="text-xs text-surface-500 line-clamp-2 leading-relaxed">
                            {{ tmpl.description }}
                        </span>
                    </button>
                </div>

                <!-- Footer: back button -->
                <div class="flex justify-start pt-4 mt-4
                            border-t border-surface-200 dark:border-surface-700">
                    <Button label="Back to form" icon="pi pi-arrow-left" text severity="secondary" type="button"
                        @click="showForm" />
                </div>
            </template>
        </template>

    </div>
</template>
