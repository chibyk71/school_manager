<script setup lang="ts">
/**
 * Components/Modals/ClassLevel/FormModal.vue
 *
 * Unified create / edit modal for a single ClassLevel.
 *
 * Modes:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Create: opened with { section, classLevel: null }
 *           POST /sections/{section}/class-levels
 * - Edit:   opened with { section, classLevel: existing }
 *           PATCH /sections/{section}/class-levels/{classLevel}
 *
 * Features implemented:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Single form handles both create and edit via isEditMode computed
 * - All fields: name, display_name, alias, description, sequence, max_arms, is_active
 * - Sequence field shows existing levels in the section as a visual guide
 *   so admin can see what positions are taken and pick the right number
 * - Live sequence conflict hint — warns inline if the entered sequence is
 *   already occupied (client-side check against the existing levels list,
 *   server also validates)
 * - is_active toggle with inline warning when deactivating an existing level
 *   that has enrolled students (warning text, not a block — server enforces)
 * - Description is a collapsible optional section to keep the form clean
 * - Uses useModalForm composable for submission, errors, loading state
 * - Emits 'saved' on success so ClassLevelsTab refreshes the DataTable
 * - Fully accessible: labels, aria-describedby on error messages, focus on
 *   first field on mount
 *
 * Props (via modal payload from ModalDirectory):
 * ─────────────────────────────────────────────────────────────────────────────
 * - section:    SchoolSection — the owning section (provides route context)
 * - classLevel: ClassLevel | null — null = create, existing = edit
 *
 * Registered in ModalDirectory.ts as 'class-level-form'
 */

import { computed, ref, watch, onMounted, nextTick } from 'vue'
import { useModalForm } from '@/composables/useModalForm'
import { useModal } from '@/composables/useModal'
import {
    InputText,
    InputNumber,
    Textarea,
    ToggleSwitch,
    Button,
    Message,
    Badge,
} from 'primevue'
import axios from 'axios'

// ── Types ─────────────────────────────────────────────────────────────────────

interface SchoolSection {
    id: string
    name: string
}

interface ClassLevel {
    id: string
    name: string
    display_name: string | null
    alias: string | null
    description: string | null
    sequence: number
    max_arms: number | null
    is_active: boolean
    class_sections_count: number
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    section: SchoolSection
    classLevel: ClassLevel | null
}>()

// ── Composables ───────────────────────────────────────────────────────────────

const modal = useModal()

// ── Mode ──────────────────────────────────────────────────────────────────────

const isEditMode = computed(() => !!props.classLevel?.id)

// ── Existing levels (for sequence guide) ─────────────────────────────────────

/**
 * Load existing levels in this section so we can show the admin which
 * sequence positions are already taken. This prevents confusion when
 * picking a sequence number.
 */
const existingLevels = ref<Array<{ id: string; name: string; sequence: number }>>([])
const loadingExisting = ref(false)

const loadExistingLevels = async () => {
    loadingExisting.value = true
    try {
        const { data } = await axios.get(
            route('class-levels.index', props.section.id),
            { params: { per_page: 100, full_load: true } }
        )
        existingLevels.value = (data.data ?? [])
            // Exclude the current level from the taken list in edit mode
            .filter((l: ClassLevel) => l.id !== props.classLevel?.id)
            .sort((a: ClassLevel, b: ClassLevel) => a.sequence - b.sequence)
    } catch {
        // Non-critical — sequence guide degrades gracefully
        existingLevels.value = []
    } finally {
        loadingExisting.value = false
    }
}

// ── Form ──────────────────────────────────────────────────────────────────────

/**
 * Next available sequence: max existing sequence + 1.
 * Used as the default when creating a new level.
 */
const nextSequence = computed(() => {
    if (existingLevels.value.length === 0) return 1
    return Math.max(...existingLevels.value.map(l => l.sequence)) + 1
})

const { form, submit, isLoading, errors } = useModalForm(
    {
        name: props.classLevel?.name ?? '',
        display_name: props.classLevel?.display_name ?? null,
        alias: props.classLevel?.alias ?? null,
        description: props.classLevel?.description ?? null,
        sequence: props.classLevel?.sequence ?? null, // null until existing levels load
        max_arms: props.classLevel?.max_arms ?? null,
        is_active: props.classLevel?.is_active ?? true,
    },
    {
        resource: 'class-levels',
        resourceId: props.classLevel?.id,
        url: isEditMode.value
            ? route('class-levels.update', {
                section: props.section.id,
                classLevel: props.classLevel!.id,
            })
            : route('class-levels.store', props.section.id),
        method: isEditMode.value ? 'patch' : 'post',
        successMessage: isEditMode.value
            ? 'Class level updated successfully.'
            : 'Class level created successfully.',
        onSuccess: () => {
            modal.emitter.value?.emit('saved')
            modal.closeCurrent()
        },
    }
)

// Set default sequence once existing levels are loaded (create mode only)
watch(nextSequence, (val) => {
    if (!isEditMode.value && form.sequence === null) {
        form.sequence = val
    }
}, { immediate: true })

// ── Sequence conflict hint ────────────────────────────────────────────────────

/**
 * Client-side check: is the currently entered sequence already taken
 * by another level in this section?
 * Server also validates — this is just a UX hint, not a hard block.
 */
const sequenceConflict = computed(() => {
    if (!form.sequence) return null
    const conflict = existingLevels.value.find(l => l.sequence === form.sequence)
    return conflict ?? null
})

// ── Deactivate warning ────────────────────────────────────────────────────────

/**
 * Show a warning when admin tries to deactivate a level that has streams.
 * The server will block it if students are enrolled — this is a soft warning.
 */
const showDeactivateWarning = computed(() =>
    isEditMode.value
    && !form.is_active
    && (props.classLevel?.class_sections_count ?? 0) > 0
)

// ── Optional sections toggle ──────────────────────────────────────────────────

const showOptionalFields = ref(isEditMode.value && (
    !!props.classLevel?.display_name ||
    !!props.classLevel?.description
))

// ── Focus first field on mount ────────────────────────────────────────────────

const nameInputRef = ref<HTMLInputElement | null>(null)

onMounted(async () => {
    await loadExistingLevels()
    await nextTick()
    nameInputRef.value?.focus()
})
</script>

<template>
    <div class="space-y-6">

        <!-- ── Mode header ────────────────────────────────────────────────── -->
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ isEditMode ? `Edit: ${classLevel!.name}` : 'Add Class Level' }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Section: <span class="font-medium">{{ section.name }}</span>
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-5" novalidate>

            <!-- ── Name ──────────────────────────────────────────────────── -->
            <div>
                <label for="cl-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Name <span class="text-red-500">*</span>
                </label>
                <InputText id="cl-name" ref="nameInputRef" v-model="form.name"
                    placeholder="e.g. JSS 1, Primary 6, Form 1" class="w-full" :invalid="!!errors.name"
                    :disabled="isLoading" aria-describedby="cl-name-error" fluid />
                <small v-if="errors.name" id="cl-name-error" class="text-red-500 text-xs mt-1 block">
                    {{ errors.name }}
                </small>
            </div>

            <!-- ── Alias ─────────────────────────────────────────────────── -->
            <div>
                <label for="cl-alias" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Short Alias
                    <span class="text-gray-400 font-normal text-xs ml-1">(optional)</span>
                </label>
                <InputText id="cl-alias" v-model="form.alias" placeholder="e.g. JS1, P6" class="w-full"
                    :invalid="!!errors.alias" :disabled="isLoading" aria-describedby="cl-alias-hint cl-alias-error"
                    fluid />
                <small id="cl-alias-hint" class="text-gray-400 text-xs mt-1 block">
                    Used in compact displays like timetables and badges.
                </small>
                <small v-if="errors.alias" id="cl-alias-error" class="text-red-500 text-xs mt-1 block">
                    {{ errors.alias }}
                </small>
            </div>

            <!-- ── Sequence + Max Arms (side by side) ────────────────────── -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Sequence -->
                <div>
                    <label for="cl-sequence" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Sequence <span class="text-red-500">*</span>
                    </label>
                    <InputNumber id="cl-sequence" v-model="form.sequence" :min="1" :max="99" :show-buttons="true"
                        placeholder="1" class="w-full" :invalid="!!errors.sequence || !!sequenceConflict"
                        :disabled="isLoading" aria-describedby="cl-sequence-hint cl-sequence-error" fluid />
                    <!-- Conflict hint -->
                    <small v-if="sequenceConflict" class="text-amber-600 dark:text-amber-400 text-xs mt-1 block">
                        Position {{ form.sequence }} is already used by
                        "{{ sequenceConflict.name }}".
                    </small>
                    <small v-else-if="errors.sequence" id="cl-sequence-error" class="text-red-500 text-xs mt-1 block">
                        {{ errors.sequence }}
                    </small>
                    <small v-else id="cl-sequence-hint" class="text-gray-400 text-xs mt-1 block">
                        Controls promotion order (lower = earlier year).
                    </small>
                </div>

                <!-- Max Arms -->
                <div>
                    <label for="cl-max-arms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Max Streams
                        <span class="text-gray-400 font-normal text-xs ml-1">(optional)</span>
                    </label>
                    <InputNumber id="cl-max-arms" v-model="form.max_arms" :min="1" :max="50" :show-buttons="true"
                        placeholder="Unlimited" class="w-full" :invalid="!!errors.max_arms" :disabled="isLoading"
                        aria-describedby="cl-max-arms-hint cl-max-arms-error" fluid />
                    <small v-if="errors.max_arms" id="cl-max-arms-error" class="text-red-500 text-xs mt-1 block">
                        {{ errors.max_arms }}
                    </small>
                    <small v-else id="cl-max-arms-hint" class="text-gray-400 text-xs mt-1 block">
                        Soft cap on classrooms under this level.
                    </small>
                </div>
            </div>

            <!-- ── Existing levels sequence guide ────────────────────────── -->
            <div v-if="existingLevels.length > 0"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                    Existing sequences in this section:
                </p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="level in existingLevels" :key="level.id"
                        class="flex items-center gap-1.5 px-2 py-1 rounded text-xs" :class="level.sequence === form.sequence
                            ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-700'
                            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600'
                            ">
                        <span class="font-mono font-semibold">{{ level.sequence }}</span>
                        <span>{{ level.name }}</span>
                    </div>
                </div>
            </div>

            <!-- ── is_active toggle ───────────────────────────────────────── -->
            <div>
                <div class="flex items-center gap-3">
                    <ToggleSwitch v-model="form.is_active" input-id="cl-is-active" :disabled="isLoading" />
                    <label for="cl-is-active"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                        Active
                    </label>
                </div>
                <small class="text-gray-400 text-xs mt-1.5 block">
                    Inactive levels are hidden from student assignment forms and dropdowns.
                </small>

                <!-- Deactivate warning -->
                <Message v-if="showDeactivateWarning" severity="warn" :closable="false" class="mt-3">
                    This level has {{ classLevel!.class_sections_count }} stream(s).
                    Deactivating it will hide it from new assignments.
                    Existing students will not be affected.
                </Message>
            </div>

            <!-- ── Optional fields toggle ─────────────────────────────────── -->
            <div>
                <button type="button"
                    class="flex items-center gap-2 text-sm text-primary hover:text-primary/80 transition-colors"
                    @click="showOptionalFields = !showOptionalFields">
                    <i :class="showOptionalFields ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs" />
                    {{ showOptionalFields ? 'Hide' : 'Show' }} optional fields
                    <span class="text-gray-400 font-normal">(display name, description)</span>
                </button>
            </div>

            <!-- ── Optional fields ────────────────────────────────────────── -->
            <template v-if="showOptionalFields">

                <!-- Display Name -->
                <div>
                    <label for="cl-display-name"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Display Name
                        <span class="text-gray-400 font-normal text-xs ml-1">(optional)</span>
                    </label>
                    <InputText id="cl-display-name" v-model="form.display_name"
                        placeholder="e.g. Junior Secondary School One" class="w-full" :invalid="!!errors.display_name"
                        :disabled="isLoading" fluid />
                    <small class="text-gray-400 text-xs mt-1 block">
                        Formal name used in reports and certificates.
                    </small>
                    <small v-if="errors.display_name" class="text-red-500 text-xs mt-1 block">
                        {{ errors.display_name }}
                    </small>
                </div>

                <!-- Description -->
                <div>
                    <label for="cl-description"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Description
                        <span class="text-gray-400 font-normal text-xs ml-1">(optional)</span>
                    </label>
                    <Textarea id="cl-description" v-model="form.description" rows="3"
                        placeholder="Internal notes about this class level..." class="w-full"
                        :invalid="!!errors.description" :disabled="isLoading" auto-resize />
                    <small v-if="errors.description" class="text-red-500 text-xs mt-1 block">
                        {{ errors.description }}
                    </small>
                </div>

            </template>

            <!-- ── Form actions ───────────────────────────────────────────── -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <Button type="button" label="Cancel" severity="secondary" text :disabled="isLoading"
                    @click="modal.closeCurrent()" />
                <Button type="submit" :label="isEditMode ? 'Save Changes' : 'Create Level'" :loading="isLoading"
                    :disabled="isLoading" />
            </div>

        </form>
    </div>
</template>
