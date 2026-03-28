<script setup lang="ts">
/**
 * BulkGenerateSectionsModal.vue
 *
 * 3-step wizard for bulk generating arm sections across one or multiple class levels.
 * Registered in ModalDirectory.ts as 'class-section-generate'.
 *
 * ── Steps ─────────────────────────────────────────────────────────────────────
 * Step 1 — Pick Class Levels
 *   Multi-select tree/list of class levels grouped by school section.
 *   "Select all" per group. Pre-selected if classLevelIds was passed via payload.
 *
 * Step 2 — Choose Naming Style
 *   Radio cards for: Alphabetic / Numeric / Precious Stones / Virtues / Colours / Custom
 *   For preset styles: slider/number input for count (1 → preset max)
 *   For custom: tag/chip input for arm names
 *   Live preview chips update as user adjusts count/names
 *
 * Step 3 — Preview & Confirm
 *   Shows exactly what will be created, per class level:
 *     JSS 1 → JSS 1A, JSS 1B, JSS 1C  (3 new, 0 skipped)
 *   Skipped arms (already exist) shown with strikethrough + "exists" label
 *   Optional: defaults (capacity, status) before confirming
 *
 * ── Props (via ModalService payload) ─────────────────────────────────────────
 * classLevelIds?   string[]   pre-selected class levels (from filter context)
 * namingPresets    NamingPresetsMap  — passed from Inertia page props
 *
 * ── API ───────────────────────────────────────────────────────────────────────
 * POST /settings/academic/class-sections/bulk-generate
 *
 * ── Response Handling ─────────────────────────────────────────────────────────
 * After success, shows a summary toast ("9 created, 0 skipped")
 * and closes the modal. Parent Index.vue refreshes via router.reload().
 */

import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'
import {
    Button,
    Checkbox,
    InputNumber,
    Chips,
    ProgressSpinner,
    Stepper,
    StepList,
    Step,
    StepPanels,
    StepPanel,
    Message,
} from 'primevue'
import type {
    NamingPresetsMap,
    NamingStyleKey,
    BulkGeneratePayload,
    BulkGenerateResult,
} from '@/types/class-section'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps<{
    classLevelIds?: string[]
    namingPresets: NamingPresetsMap
    // The full list of class levels for selection, grouped by school section
    // Shape: [{ schoolSection: string, levels: { id, name }[] }]
    availableLevels: Array<{
        school_section: string
        levels: Array<{ id: string; name: string; display_name: string | null }>
    }>
}>()

const emit = defineEmits<{ close: []; generated: [] }>()
const toast = useToast()

// ── Wizard state ─────────────────────────────────────────────────────────────
const step = ref(1)  // 1 | 2 | 3
const loading = ref(false)
const result = ref<BulkGenerateResult | null>(null)

// ── Step 1: Level selection ───────────────────────────────────────────────────
const selectedLevelIds = ref<string[]>(props.classLevelIds ?? [])

const allLevelIds = computed(() =>
    props.availableLevels.flatMap(g => g.levels.map(l => l.id))
)

const isGroupSelected = (levels: { id: string }[]) =>
    levels.every(l => selectedLevelIds.value.includes(l.id))

const toggleGroup = (levels: { id: string }[]) => {
    const ids = levels.map(l => l.id)
    const allSelected = ids.every(id => selectedLevelIds.value.includes(id))
    if (allSelected) {
        selectedLevelIds.value = selectedLevelIds.value.filter(id => !ids.includes(id))
    } else {
        selectedLevelIds.value = [...new Set([...selectedLevelIds.value, ...ids])]
    }
}

const toggleAll = () => {
    if (selectedLevelIds.value.length === allLevelIds.value.length) {
        selectedLevelIds.value = []
    } else {
        selectedLevelIds.value = [...allLevelIds.value]
    }
}

// ── Step 2: Naming ────────────────────────────────────────────────────────────
const namingStyle = ref<NamingStyleKey>('alphabetic')
const armCount = ref(3)
const customArms = ref<string[]>([])

const selectedPreset = computed(() =>
    namingStyle.value !== 'custom'
        ? props.namingPresets[namingStyle.value]
        : null
)

const maxCount = computed(() =>
    selectedPreset.value?.max_count ?? 10
)

// Clamp arm count when preset changes
watch(namingStyle, () => {
    if (namingStyle.value !== 'custom') {
        armCount.value = Math.min(armCount.value, maxCount.value)
    }
})

// Arms that will be generated for preview
const previewArms = computed((): string[] => {
    if (namingStyle.value === 'custom') {
        return customArms.value.map(a => a.trim()).filter(Boolean)
    }
    return selectedPreset.value?.arms.slice(0, armCount.value) ?? []
})

// Style definitions for the radio cards
const styleCards: Array<{ key: NamingStyleKey; icon: string }> = [
    { key: 'alphabetic', icon: 'pi pi-sort-alpha-down' },
    { key: 'numeric', icon: 'pi pi-sort-numeric-down' },
    { key: 'precious', icon: 'pi pi-sparkles' },
    { key: 'virtues', icon: 'pi pi-star' },
    { key: 'colours', icon: 'pi pi-palette' },
    { key: 'custom', icon: 'pi pi-pencil' },
]

// ── Step 3: Defaults + Preview ────────────────────────────────────────────────
const defaultCapacity = ref<number>(0)
const defaultStatus = ref<'active' | 'inactive'>('active')

// Flat list of selected levels for preview
const selectedLevels = computed(() =>
    props.availableLevels
        .flatMap(g => g.levels)
        .filter(l => selectedLevelIds.value.includes(l.id))
)

// Total arms that will be attempted
const totalAttempts = computed(() =>
    selectedLevels.value.length * previewArms.value.length
)

// ── Step navigation ───────────────────────────────────────────────────────────
const step1Valid = computed(() => selectedLevelIds.value.length > 0)
const step2Valid = computed(() => {
    if (namingStyle.value === 'custom') return customArms.value.length > 0
    return armCount.value >= 1
})

const nextStep = () => {
    if (step.value === 1 && step1Valid.value) step.value = 2
    else if (step.value === 2 && step2Valid.value) step.value = 3
}

const prevStep = () => {
    if (step.value > 1) step.value--
}

// ── Submit ────────────────────────────────────────────────────────────────────
const submit = async () => {
    loading.value = true

    const payload: BulkGeneratePayload = {
        class_level_ids: selectedLevelIds.value,
        naming_style: namingStyle.value,
        defaults: {
            capacity: defaultCapacity.value,
            status: defaultStatus.value,
        },
    }

    if (namingStyle.value === 'custom') {
        payload.custom_arms = previewArms.value
    } else {
        payload.arm_count = armCount.value
    }

    try {
        const { data } = await axios.post<BulkGenerateResult>(
            route('settings.academic.class-sections.bulk-generate'),
            payload
        )

        result.value = data

        toast.add({
            severity: 'success',
            summary: 'Sections Generated',
            detail: data.message,
            life: 5000,
        })

        emit('generated')
        emit('close')
    } catch (err: any) {
        const message = err.response?.data?.message
            ?? Object.values(err.response?.data?.errors ?? {})[0]
            ?? 'Generation failed. Please try again.'

        toast.add({ severity: 'error', summary: 'Error', detail: message as string, life: 6000 })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="flex flex-col" style="min-height: 500px">

        <!-- Step indicator ──────────────────────────────────────────────── -->
        <Stepper :value="step" class="px-6 pt-2 pb-4 border-b border-gray-200 dark:border-gray-700" linear>
            <StepList>
                <Step :value="1">Select Levels</Step>
                <Step :value="2">Naming Style</Step>
                <Step :value="3">Preview & Confirm</Step>
            </StepList>
        </Stepper>

        <!-- Step panels ─────────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-6">

            <!-- ── STEP 1: Level selection ────────────────────────────── -->
            <template v-if="step === 1">
                <div class="space-y-4">

                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Select the class levels to generate arms for.
                        </p>
                        <button type="button"
                            class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium"
                            @click="toggleAll">
                            {{ selectedLevelIds.length === allLevelIds.length ? 'Deselect all' : 'Select all' }}
                        </button>
                    </div>

                    <!-- Grouped by school section ─────────────────────── -->
                    <div v-for="group in availableLevels" :key="group.school_section"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Group header with "select group" toggle -->
                        <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50 dark:bg-gray-800
                                   border-b border-gray-200 dark:border-gray-700 cursor-pointer
                                   hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors"
                            @click="toggleGroup(group.levels)">
                            <Checkbox :model-value="isGroupSelected(group.levels)" binary
                                @click.stop="toggleGroup(group.levels)" />
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ group.school_section }}
                            </span>
                            <span class="ml-auto text-xs text-gray-400">
                                {{group.levels.filter(l => selectedLevelIds.includes(l.id)).length}}
                                / {{ group.levels.length }} selected
                            </span>
                        </div>

                        <!-- Levels in the group ───────────────────────── -->
                        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                            <label v-for="level in group.levels" :key="level.id" class="flex items-center gap-3 px-4 py-2.5 cursor-pointer
                                       hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <Checkbox v-model="selectedLevelIds" :value="level.id" binary />
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ level.display_name ?? level.name }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <Message v-if="availableLevels.length === 0" severity="info" :closable="false">
                        No class levels found. Create class levels first.
                    </Message>

                </div>
            </template>

            <!-- ── STEP 2: Naming style ───────────────────────────────── -->
            <template v-if="step === 2">
                <div class="space-y-6">

                    <!-- Style radio cards ─────────────────────────────── -->
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            Choose how the arms will be named.
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <button v-for="card in styleCards" :key="card.key" type="button" class="flex flex-col items-start p-3.5 rounded-lg border-2 text-left
                                       transition-all duration-150 focus:outline-none focus:ring-2
                                       focus:ring-primary-500 focus:ring-offset-1"
                                :class="namingStyle === card.key
                                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'" @click="namingStyle = card.key">
                                <i :class="[card.icon, 'text-lg mb-1',
                                namingStyle === card.key
                                    ? 'text-primary-600 dark:text-primary-400'
                                    : 'text-gray-400']" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ card.key === 'custom'
                                        ? 'Custom'
                                        : namingPresets[card.key]?.label }}
                                </span>
                                <span v-if="card.key !== 'custom'" class="text-xs text-gray-400 mt-0.5 leading-tight">
                                    {{ namingPresets[card.key]?.arms.slice(0, 3).join(', ') }}...
                                </span>
                                <span v-else class="text-xs text-gray-400 mt-0.5">
                                    Type your own names
                                </span>
                            </button>
                        </div>

                        <!-- Style description -->
                        <p v-if="selectedPreset" class="text-xs text-gray-500 dark:text-gray-400 mt-3 italic">
                            {{ selectedPreset.description }}
                        </p>
                    </div>

                    <!-- Count slider (preset styles) ─────────────────── -->
                    <div v-if="namingStyle !== 'custom'" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Number of arms
                            </label>
                            <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                {{ armCount }}
                            </span>
                        </div>
                        <InputNumber v-model="armCount" :min="1" :max="maxCount" show-buttons button-layout="horizontal"
                            fluid :decrement-button-props="{ severity: 'secondary', text: true }"
                            :increment-button-props="{ severity: 'secondary', text: true }" />
                        <p class="text-xs text-gray-400">
                            Max {{ maxCount }} for {{ selectedPreset?.label }} style
                        </p>
                    </div>

                    <!-- Custom arm names ─────────────────────────────── -->
                    <div v-if="namingStyle === 'custom'" class="space-y-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom arm names
                            <span class="text-red-500">*</span>
                        </label>
                        <Chips v-model="customArms" placeholder="Type a name and press Enter..." class="w-full"
                            separator="," />
                        <p class="text-xs text-gray-400">
                            Press Enter or comma to add each name.
                            Up to 10 arms allowed.
                        </p>
                    </div>

                    <!-- Live preview chips ───────────────────────────── -->
                    <div v-if="previewArms.length > 0" class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4">
                        <p
                            class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2.5">
                            Preview — first level
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="arm in previewArms" :key="arm" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                       bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                {{ arm }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-2.5">
                            {{ previewArms.length }} arm{{ previewArms.length !== 1 ? 's' : '' }}
                            × {{ selectedLevelIds.length }} level{{ selectedLevelIds.length !== 1 ? 's' : '' }}
                            = up to {{ totalAttempts }} sections
                        </p>
                    </div>

                </div>
            </template>

            <!-- ── STEP 3: Preview & confirm ─────────────────────────── -->
            <template v-if="step === 3">
                <div class="space-y-5">

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Review what will be created. Existing arms are automatically skipped.
                    </p>

                    <!-- Per-level preview grid ───────────────────────── -->
                    <div class="space-y-3">
                        <div v-for="level in selectedLevels" :key="level.id"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">
                                {{ level.display_name ?? level.name }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="arm in previewArms" :key="arm"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                           bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                    {{ (level.display_name ?? level.name).trimEnd() }}{{ arm.length === 1 ? '' : ' '
                                    }}{{ arm }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Optional defaults ────────────────────────────── -->
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Apply to all generated sections
                        </p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Default Capacity
                                    <span class="font-normal text-gray-400">(0 = uncapped)</span>
                                </label>
                                <InputNumber v-model="defaultCapacity" :min="0" :max="1000" placeholder="0" fluid />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Default Status
                                </label>
                                <div class="flex gap-3 pt-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="defaultStatus" value="active" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="defaultStatus" value="inactive" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Inactive</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary callout ──────────────────────────────── -->
                    <div
                        class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>{{ totalAttempts }}</strong> sections will be attempted across
                            <strong>{{ selectedLevels.length }}</strong> class level{{ selectedLevels.length !== 1 ? 's'
                            : '' }}.
                            Sections that already exist will be skipped automatically.
                        </p>
                    </div>

                </div>
            </template>

        </div>

        <!-- Footer ──────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between px-6 py-4 border-t
                   border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <!-- Back / Cancel -->
            <div>
                <Button v-if="step > 1" label="Back" icon="pi pi-arrow-left" severity="secondary" text
                    @click="prevStep" />
                <Button v-else label="Cancel" severity="secondary" text @click="emit('close')" />
            </div>

            <!-- Next / Generate -->
            <Button v-if="step < 3" label="Next" icon="pi pi-arrow-right" icon-pos="right"
                :disabled="(step === 1 && !step1Valid) || (step === 2 && !step2Valid)" @click="nextStep" />
            <Button v-else label="Generate Sections" icon="pi pi-bolt" :loading="loading"
                :disabled="totalAttempts === 0" @click="submit" />
        </div>

    </div>
</template>
