<script setup lang="ts">
/**
 * Components/Modals/ClassLevel/BulkGenerateModal.vue
 *
 * Preset picker modal for bulk-generating class levels in a section.
 *
 * Flow:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Modal mounts → loads preset tree from GET /class-levels/presets
 *    and existing level names from GET /class-levels (for skip detection)
 * 2. Admin picks a variant from the CascadeSelect
 *    (curriculum → group → variant)
 * 3. Preview panel renders immediately from the tree data (no extra request)
 *    - Green rows: will be created
 *    - Gray rows: already exist, will be skipped
 * 4. Admin clicks "Generate" → POST /sections/{section}/class-levels/bulk-generate
 * 5. Success → emits 'generated' → ClassLevelsTab refreshes → modal closes
 *
 * Features:
 * ─────────────────────────────────────────────────────────────────────────────
 * - CascadeSelect maps directly to ClassLevelPresets::toTree() shape
 * - Group node selection auto-resolves to the group's default variant
 * - Preview panel shows create/skip status for each level in the selection
 * - Summary badge: "X will be created, Y already exist"
 * - Disabled Generate button when nothing selected or all levels would be skipped
 * - Graceful loading and error states for both preset and existing level fetches
 *
 * Props (via modal payload):
 * ─────────────────────────────────────────────────────────────────────────────
 * - section: SchoolSection — the owning section
 *
 * Registered in ModalDirectory.ts as 'class-level-bulk-generate'
 */

import { ref, computed, onMounted } from 'vue'
import { useModal } from '@/composables/useModal'
import { useToast } from 'primevue/usetoast'
import { Button, CascadeSelect, Message, ProgressSpinner, Badge } from 'primevue'
import axios from 'axios'

// ── Types ─────────────────────────────────────────────────────────────────────

interface SchoolSection {
    id: string
    name: string
}

/**
 * Leaf node (variant) — selectable in the cascade select.
 * Matches the shape returned by ClassLevelPresets::toTree() for leaf nodes.
 */
interface PresetVariant {
    label: string
    key: string       // full dot-notation key e.g. 'nigerian.primary_school.p1_6'
    preview: string[] // level names that will be created
    count: number
}

/**
 * Group node — intermediate level in the cascade select.
 * Selecting a group auto-resolves to its defaultKey.
 */
interface PresetGroup {
    label: string
    key: string
    defaultKey: string  // full key of the default variant for this group
    children: PresetVariant[]
}

/**
 * Curriculum node — root level in the cascade select.
 */
interface PresetCurriculum {
    label: string
    key: string
    children: PresetGroup[]
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    section: SchoolSection
}>()

// ── Composables ───────────────────────────────────────────────────────────────

const modal = useModal()
const toast = useToast()

// ── State ─────────────────────────────────────────────────────────────────────

const presets = ref<PresetCurriculum[]>([])
const loadingPresets = ref(false)
const presetsError = ref<string | null>(null)

const existingNames = ref<string[]>([])
const loadingExisting = ref(false)

/** The currently selected cascade value (a variant node) */
const selectedVariant = ref<PresetVariant | null>(null)

const submitting = ref(false)

// ── Data loading ──────────────────────────────────────────────────────────────

const loadPresets = async () => {
    loadingPresets.value = true
    presetsError.value = null
    try {
        const { data } = await axios.get(
            route('class-levels.presets', props.section.id)
        )
        presets.value = data.presets ?? []
    } catch {
        presetsError.value = 'Failed to load presets. Please try again.'
    } finally {
        loadingPresets.value = false
    }
}

const loadExistingNames = async () => {
    loadingExisting.value = true
    try {
        const { data } = await axios.get(
            route('class-levels.index', props.section.id),
            { params: { full_load: true, per_page: 200 } }
        )
        existingNames.value = (data.data ?? [])
            .map((l: { name: string }) => l.name.toLowerCase().trim())
    } catch {
        // Non-critical — skip detection degrades gracefully
        existingNames.value = []
    } finally {
        loadingExisting.value = false
    }
}

onMounted(async () => {
    await Promise.all([loadPresets(), loadExistingNames()])
})

// ── Cascade select: option labels & children keys ─────────────────────────────

/**
 * PrimeVue CascadeSelect needs to know:
 * - optionLabel:        which property to show as the display text
 * - optionGroupLabel:   same for group nodes
 * - optionGroupChildren: which property holds the next level's array
 *
 * Our tree has two levels of grouping before the selectable leaf:
 *   Curriculum (children: Group[])
 *     Group    (children: Variant[])  ← Variant is the selectable leaf
 */
const cascadeOptionLabel = 'label'
const cascadeOptionGroupLabel = 'label'
const cascadeOptionGroupChildren = ['children', 'children'] // two levels deep

// ── Selection handling ────────────────────────────────────────────────────────

/**
 * Called when CascadeSelect fires a change event.
 * The selected value may be a variant leaf OR a group node.
 *
 * If it's a group node (has `defaultKey`, not a direct `key` pointing to a variant),
 * we resolve the default variant from the preset tree and use that instead.
 */
const onSelectionChange = (value: PresetVariant | PresetGroup | null) => {
    if (!value) {
        selectedVariant.value = null
        return
    }

    // Group node selected — resolve to its default variant
    if ('defaultKey' in value && value.defaultKey) {
        const defaultVariant = findVariantByKey(value.defaultKey)
        selectedVariant.value = defaultVariant
        return
    }

    // Variant leaf selected directly
    selectedVariant.value = value as PresetVariant
}

/**
 * Walk the preset tree to find a variant node by its full dot-notation key.
 */
const findVariantByKey = (key: string): PresetVariant | null => {
    for (const curriculum of presets.value) {
        for (const group of curriculum.children) {
            const found = group.children.find(v => v.key === key)
            if (found) return found
        }
    }
    return null
}

// ── Preview computation ───────────────────────────────────────────────────────

interface PreviewRow {
    name: string
    willBeCreated: boolean // false = already exists, will be skipped
}

const previewRows = computed<PreviewRow[]>(() => {
    if (!selectedVariant.value) return []

    return selectedVariant.value.preview.map(name => ({
        name,
        willBeCreated: !existingNames.value.includes(name.toLowerCase().trim()),
    }))
})

const willCreateCount = computed(() =>
    previewRows.value.filter(r => r.willBeCreated).length
)

const willSkipCount = computed(() =>
    previewRows.value.filter(r => !r.willBeCreated).length
)

/**
 * Disable the generate button if:
 * - Nothing is selected
 * - All levels in the selection already exist (nothing would be created)
 * - Currently submitting
 */
const canGenerate = computed(() =>
    !!selectedVariant.value
    && willCreateCount.value > 0
    && !submitting.value
)

// ── Submit ────────────────────────────────────────────────────────────────────

const generate = async () => {
    if (!canGenerate.value || !selectedVariant.value) return

    submitting.value = true

    try {
        const { data } = await axios.post(
            route('class-levels.bulk-generate', props.section.id),
            { preset_key: selectedVariant.value.key }
        )

        toast.add({
            severity: 'success',
            summary: 'Levels Generated',
            detail: data.message,
            life: 5000,
        })

        modal.emitter.value?.emit('generated')
        modal.closeCurrent()

    } catch (e: any) {
        const message = e.response?.data?.message
            ?? e.response?.data?.errors?.preset_key?.[0]
            ?? 'Failed to generate class levels. Please try again.'

        toast.add({
            severity: 'error',
            summary: 'Generation Failed',
            detail: message,
            life: 6000,
        })
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="space-y-6">

        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Generate Class Levels
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Choose a preset to auto-create class levels for
                <span class="font-medium">{{ section.name }}</span>.
                Levels that already exist will be skipped.
            </p>
        </div>

        <!-- ── Preset loading error ───────────────────────────────────────── -->
        <Message v-if="presetsError" severity="error" :closable="false">
            {{ presetsError }}
            <Button label="Retry" text size="small" class="ml-2" @click="loadPresets" />
        </Message>

        <!-- ── Loading state ──────────────────────────────────────────────── -->
        <div v-else-if="loadingPresets" class="flex items-center justify-center py-10 gap-3 text-gray-500">
            <ProgressSpinner style="width: 24px; height: 24px" />
            <span class="text-sm">Loading presets...</span>
        </div>

        <!-- ── Preset selector ────────────────────────────────────────────── -->
        <div v-else>
            <label for="preset-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Select Preset
            </label>

            <CascadeSelect id="preset-select" :options="presets" :option-label="cascadeOptionLabel"
                :option-group-label="cascadeOptionGroupLabel" :option-group-children="cascadeOptionGroupChildren"
                placeholder="Choose a curriculum and level group..." class="w-full" :disabled="submitting"
                @change="onSelectionChange($event.value)">
                <!--
                    Custom option template so group nodes show a ▸ chevron hint
                    and leaf nodes show their level count badge.
                -->
                <template #option="{ option }">
                    <div class="flex items-center justify-between w-full gap-3">
                        <span>{{ option.label }}</span>
                        <!-- Leaf variant: show count badge -->
                        <Badge v-if="option.count" :value="`${option.count} levels`" severity="secondary"
                            class="text-xs" />
                    </div>
                </template>
            </CascadeSelect>
        </div>

        <!-- ── Preview panel ──────────────────────────────────────────────── -->
        <Transition name="preview-fade">
            <div v-if="selectedVariant && previewRows.length"
                class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <!-- Preview header -->
                <div
                    class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Preview — {{ selectedVariant.label }}
                    </span>

                    <!-- Summary badges -->
                    <div class="flex items-center gap-2">
                        <Badge v-if="willCreateCount > 0" :value="`${willCreateCount} will be created`"
                            severity="success" class="text-xs" />
                        <Badge v-if="willSkipCount > 0" :value="`${willSkipCount} already exist`" severity="secondary"
                            class="text-xs" />
                    </div>
                </div>

                <!-- Preview rows -->
                <ul class="divide-y divide-gray-100 dark:divide-gray-700/50 max-h-64 overflow-y-auto">
                    <li v-for="(row, index) in previewRows" :key="index"
                        class="flex items-center justify-between px-4 py-2.5" :class="row.willBeCreated
                            ? 'bg-white dark:bg-gray-900'
                            : 'bg-gray-50/70 dark:bg-gray-800/50'">
                        <div class="flex items-center gap-3">
                            <!-- Sequence bubble -->
                            <span
                                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-mono font-semibold flex-shrink-0"
                                :class="row.willBeCreated
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500'">
                                {{ index + 1 }}
                            </span>

                            <!-- Level name -->
                            <span class="text-sm" :class="row.willBeCreated
                                ? 'text-gray-900 dark:text-white font-medium'
                                : 'text-gray-400 dark:text-gray-500 line-through'">
                                {{ row.name }}
                            </span>
                        </div>

                        <!-- Status tag -->
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="row.willBeCreated
                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500'">
                            {{ row.willBeCreated ? 'New' : 'Skip' }}
                        </span>
                    </li>
                </ul>

                <!-- All-skip warning -->
                <div v-if="willCreateCount === 0"
                    class="px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border-t border-amber-200 dark:border-amber-700/50">
                    <p class="text-sm text-amber-700 dark:text-amber-400 flex items-center gap-2">
                        <i class="pi pi-exclamation-triangle" />
                        All levels in this preset already exist in this section.
                        Select a different preset or add levels manually.
                    </p>
                </div>
            </div>
        </Transition>

        <!-- ── Actions ────────────────────────────────────────────────────── -->
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
            <Button label="Cancel" severity="secondary" text :disabled="submitting" @click="modal.closeCurrent()" />
            <Button label="Generate Levels" icon="pi pi-magic-wand" :loading="submitting" :disabled="!canGenerate"
                @click="generate" />
        </div>

    </div>
</template>

<style scoped>
.preview-fade-enter-active,
.preview-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.preview-fade-enter-from,
.preview-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
