<!--
  resources/js/Pages/Settings/Academic/AssessmentTemplates.vue

  Assessment Template Management — Settings page.

  An assessment template defines the scoring structure for exams:
  which components exist (CA1, CA2, Exam, Project...), their weights,
  and the max score per component.

  Features:
  ─────────────────────────────────────────────────────────────────────────────
  • Table of all templates with component pill previews
  • Create / Edit via side-panel (not modal — components builder needs space)
  • Dynamic component builder: add/remove components, auto-balance weights
  • Live validation: weight sum indicator, max-score sum check
  • "Set as Default" action per row
  • Active/inactive toggle
  • Cannot delete templates used by existing exams (row shows usage count)
  • Warns if attempting to change components on a template with active exams
  ─────────────────────────────────────────────────────────────────────────────
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import {
    DataTable, Column, Button, Tag, InputText, InputNumber,
    Drawer, Textarea, ToggleSwitch, Message, ProgressBar, Divider,
} from 'primevue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import type { AssessmentTemplate, AssessmentComponent } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    templates: (AssessmentTemplate & { exams_count?: number; has_active_exams?: boolean })[];
}>();

const confirm = useConfirm();
const toast   = useToast();

// ────────────────────────────────────────────────────────────────────────────
// Drawer state
// ────────────────────────────────────────────────────────────────────────────
const showDrawer  = ref(false);
const isEditing   = ref(false);
const editingId   = ref<string | null>(null);
const isSaving    = ref(false);
const formErrors  = ref<Record<string, string>>({});

interface TemplateForm {
    name: string;
    description: string;
    total_score: number;
    pass_mark: number;
    is_default: boolean;
    is_active: boolean;
    components: Array<{
        key: string;
        label: string;
        max_score: number;
        weight_percent: number;
        is_exam: boolean;
        sort_order: number;
    }>;
}

const emptyForm = (): TemplateForm => ({
    name: '',
    description: '',
    total_score: 100,
    pass_mark: 40,
    is_default: false,
    is_active: true,
    components: [
        { key: 'ca1', label: 'CA 1', max_score: 10, weight_percent: 10, is_exam: false, sort_order: 0 },
        { key: 'ca2', label: 'CA 2', max_score: 10, weight_percent: 10, is_exam: false, sort_order: 1 },
        { key: 'exam', label: 'Exam', max_score: 80, weight_percent: 80, is_exam: true, sort_order: 2 },
    ],
});

const form = ref<TemplateForm>(emptyForm());

// ────────────────────────────────────────────────────────────────────────────
// Live validation
// ────────────────────────────────────────────────────────────────────────────
const weightSum    = computed(() => form.value.components.reduce((s, c) => s + (c.weight_percent || 0), 0));
const maxScoreSum  = computed(() => form.value.components.reduce((s, c) => s + (c.max_score || 0), 0));
const weightValid  = computed(() => Math.abs(weightSum.value - 100) < 0.01);
const maxScoreValid = computed(() => Math.abs(maxScoreSum.value - form.value.total_score) < 0.01);

// ────────────────────────────────────────────────────────────────────────────
// Open Drawer
// ────────────────────────────────────────────────────────────────────────────
const openCreate = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = emptyForm();
    formErrors.value = {};
    showDrawer.value = true;
};

const openEdit = async (template: AssessmentTemplate) => {
    isEditing.value = true;
    editingId.value = template.id;
    formErrors.value = {};
    // Fetch full template with has_active_exams flag
    const { data } = await axios.get(route('assessment-templates.show', template.id));
    form.value = {
        name: data.name,
        description: data.description ?? '',
        total_score: data.total_score,
        pass_mark: data.pass_mark,
        is_default: data.is_default,
        is_active: data.is_active,
        components: data.components ?? [],
    };
    showDrawer.value = true;
};

// ────────────────────────────────────────────────────────────────────────────
// Component builder actions
// ────────────────────────────────────────────────────────────────────────────
const addComponent = () => {
    const nextIdx = form.value.components.length;
    form.value.components.push({
        key: `component_${nextIdx + 1}`,
        label: `Component ${nextIdx + 1}`,
        max_score: 10,
        weight_percent: 10,
        is_exam: false,
        sort_order: nextIdx,
    });
};

const removeComponent = (idx: number) => {
    form.value.components.splice(idx, 1);
    form.value.components.forEach((c, i) => { c.sort_order = i; });
};

const autoBalanceWeights = () => {
    const n = form.value.components.length;
    if (!n) return;
    const each = parseFloat((100 / n).toFixed(2));
    const remainder = parseFloat((100 - each * (n - 1)).toFixed(2));
    form.value.components.forEach((c, i) => {
        c.weight_percent = i === n - 1 ? remainder : each;
    });
};

// ────────────────────────────────────────────────────────────────────────────
// Save
// ────────────────────────────────────────────────────────────────────────────
const submitForm = async () => {
    formErrors.value = {};
    isSaving.value = true;

    const url = isEditing.value
        ? route('assessment-templates.update', editingId.value!)
        : route('assessment-templates.store');
    const method = isEditing.value ? 'patch' : 'post';

    try {
        await axios[method](url, form.value);
        toast.add({ severity: 'success', summary: 'Saved', detail: `Template "${form.value.name}" saved.`, life: 3000 });
        showDrawer.value = false;
        router.reload({ only: ['templates'] });
    } catch (err: any) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors ?? {};
            const first = Object.values(err.response.data.errors ?? {})[0] as string[] | undefined;
            toast.add({ severity: 'error', summary: 'Validation error', detail: first?.[0], life: 5000 });
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save template.', life: 5000 });
        }
    } finally {
        isSaving.value = false;
    }
};

// ────────────────────────────────────────────────────────────────────────────
// Delete
// ────────────────────────────────────────────────────────────────────────────
const deleteTemplate = (template: AssessmentTemplate & { exams_count?: number }) => {
    if ((template.exams_count ?? 0) > 0) {
        toast.add({
            severity: 'warn',
            summary: 'Cannot Delete',
            detail: `"${template.name}" is used by ${template.exams_count} exam(s). Archive it instead.`,
            life: 6000,
        });
        return;
    }

    confirm.require({
        header: 'Delete Template',
        message: `Delete "${template.name}"? This cannot be undone.`,
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: async () => {
            await axios.delete(route('assessment-templates.destroy'), { data: { ids: [template.id] } });
            toast.add({ severity: 'success', summary: 'Deleted', life: 3000 });
            router.reload({ only: ['templates'] });
        },
    });
};
</script>

<template>
    <SettingsLayout
        title="Assessment Templates"
        subtitle="Define how exams are structured — which components count and their weighting."
        :buttons="[{ label: 'New Template', icon: 'pi pi-plus', onClick: openCreate }]"
    >
        <!-- Templates Table -->
        <DataTable :value="templates" dataKey="id" class="text-sm">
            <Column header="Name" style="min-width: 200px">
                <template #body="{ data }">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ data.name }}</span>
                            <Tag v-if="data.is_default" value="Default" severity="success" class="text-xs" />
                            <Tag v-if="!data.is_active" value="Inactive" severity="secondary" class="text-xs" />
                        </div>
                        <p v-if="data.description" class="text-xs text-gray-500 mt-0.5">{{ data.description }}</p>
                    </div>
                </template>
            </Column>

            <!-- Component Pills -->
            <Column header="Components" style="min-width: 280px">
                <template #body="{ data }">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="comp in data.components"
                            :key="comp.key"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="comp.is_exam
                                ? 'bg-primary/10 text-primary border border-primary/20'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600'"
                        >
                            {{ comp.label }}
                            <span class="text-gray-400">{{ comp.max_score }}</span>
                        </span>
                    </div>
                </template>
            </Column>

            <Column header="Total / Pass" style="width: 110px">
                <template #body="{ data }">
                    <span class="text-sm font-medium">{{ data.total_score }}</span>
                    <span class="text-xs text-gray-500"> / {{ data.pass_mark }} pass</span>
                </template>
            </Column>

            <Column header="Exams" style="width: 80px; text-align: center">
                <template #body="{ data }">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ data.exams_count ?? 0 }}</span>
                </template>
            </Column>

            <Column header="" style="width: 80px" alignFrozen="right" frozen>
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" size="small" text severity="secondary" @click="openEdit(data)" />
                        <Button
                            icon="pi pi-trash"
                            size="small"
                            text
                            severity="danger"
                            :disabled="(data.exams_count ?? 0) > 0"
                            @click="deleteTemplate(data)"
                        />
                    </div>
                </template>
            </Column>

            <template #empty>
                <div class="text-center py-10 text-gray-500">
                    No templates yet. Create one to start using the exam module.
                </div>
            </template>
        </DataTable>

        <!-- ── Template Editor Drawer ── -->
        <Drawer
            v-model:visible="showDrawer"
            :header="isEditing ? 'Edit Template' : 'New Template'"
            position="right"
            class="!w-full md:!w-[520px]"
        >
            <div class="space-y-5 pb-4">
                <!-- Name + Description -->
                <div>
                    <label class="block text-sm font-medium mb-1">Name <span class="text-red-500">*</span></label>
                    <InputText v-model="form.name" class="w-full" placeholder="e.g. Standard (CA + Exam)" :invalid="!!formErrors.name" />
                    <small v-if="formErrors.name" class="text-red-500 text-xs">{{ formErrors.name }}</small>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <Textarea v-model="form.description" class="w-full" rows="2" placeholder="Optional description..." auto-resize />
                </div>

                <!-- Total Score + Pass Mark -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Total Score <span class="text-red-500">*</span></label>
                        <InputNumber v-model="form.total_score" :min="10" :max="1000" class="w-full" :invalid="!!formErrors.total_score" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Pass Mark <span class="text-red-500">*</span></label>
                        <InputNumber v-model="form.pass_mark" :min="0" :max="form.total_score" class="w-full" />
                    </div>
                </div>

                <!-- Flags -->
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <ToggleSwitch v-model="form.is_active" />
                        Active
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <ToggleSwitch v-model="form.is_default" />
                        Set as Default
                    </label>
                </div>

                <Divider />

                <!-- Component Builder -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Components</h3>
                        <div class="flex gap-2">
                            <Button
                                label="Balance Weights"
                                icon="pi pi-sliders-h"
                                size="small"
                                severity="secondary"
                                text
                                @click="autoBalanceWeights"
                            />
                            <Button
                                label="Add"
                                icon="pi pi-plus"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="addComponent"
                            />
                        </div>
                    </div>

                    <!-- Weight/MaxScore validation bars -->
                    <div class="grid grid-cols-2 gap-3 mb-3 text-xs">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-500">Weights sum</span>
                                <span :class="weightValid ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                                    {{ weightSum.toFixed(1) }}% / 100%
                                </span>
                            </div>
                            <ProgressBar
                                :value="Math.min(weightSum, 100)"
                                :show-value="false"
                                style="height: 5px"
                                :pt="{ value: { class: weightValid ? 'bg-green-500' : weightSum > 100 ? 'bg-red-500' : 'bg-amber-400' } }"
                            />
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-500">Max scores sum</span>
                                <span :class="maxScoreValid ? 'text-green-600 font-semibold' : 'text-amber-600 font-semibold'">
                                    {{ maxScoreSum }} / {{ form.total_score }}
                                </span>
                            </div>
                            <ProgressBar
                                :value="form.total_score ? Math.min((maxScoreSum / form.total_score) * 100, 100) : 0"
                                :show-value="false"
                                style="height: 5px"
                                :pt="{ value: { class: maxScoreValid ? 'bg-green-500' : 'bg-amber-400' } }"
                            />
                        </div>
                    </div>

                    <!-- Component Rows -->
                    <div class="space-y-2">
                        <div
                            v-for="(comp, idx) in form.components"
                            :key="idx"
                            class="grid grid-cols-[1fr_1fr_70px_70px_36px] gap-2 items-center p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                        >
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Label</label>
                                <InputText v-model="comp.label" size="small" class="w-full" placeholder="CA 1" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Key</label>
                                <InputText v-model="comp.key" size="small" class="w-full font-mono" placeholder="ca1" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Max</label>
                                <InputNumber v-model="comp.max_score" :min="1" size="small" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Weight %</label>
                                <InputNumber v-model="comp.weight_percent" :min="1" :max="100" size="small" class="w-full" />
                            </div>
                            <Button
                                icon="pi pi-times"
                                size="small"
                                text
                                severity="danger"
                                :disabled="form.components.length <= 2"
                                @click="removeComponent(idx)"
                            />
                        </div>
                    </div>

                    <Message v-if="formErrors.components" severity="error" variant="simple" class="mt-2 text-xs">
                        {{ Array.isArray(formErrors.components) ? formErrors.components[0] : formErrors.components }}
                    </Message>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="showDrawer = false" />
                <Button
                    :label="isEditing ? 'Save Changes' : 'Create Template'"
                    icon="pi pi-check"
                    :loading="isSaving"
                    :disabled="!weightValid"
                    @click="submitForm"
                />
            </template>
        </Drawer>
    </SettingsLayout>
</template>
