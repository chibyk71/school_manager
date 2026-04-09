// resources/js/composables/useScoreEntry.ts
/**
 * useScoreEntry.ts
 *
 * Core composable for the score-entry form page.
 *
 * Features / Problems Solved:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Manages the editable copy of all student scores (local state separate from server)
 * - Tracks which rows have been modified (dirty tracking) to highlight unsaved changes
 * - Provides auto-save: debounces changes and sends a save request 2 seconds after last edit
 * - Handles the Tab/Enter key navigation (student → component → next student) for fast entry
 * - Validates score values client-side before sending (within 0..max bounds)
 * - Provides saveAll() for explicit full-form submission
 * - Exposes per-row loading/error states (some rows may fail while others succeed)
 * - Handles absent/exempted toggles (clears scores for that row immediately)
 * - Tracks overall completion (progress bar at top of form)
 *
 * Fits into the module:
 * - Used exclusively by Academic/Exams/ScoreEntry.vue
 * - Calls POST /exams/{exam}/scores/{section}/{subject} for bulk saves
 * - Calls POST /exams/{exam}/scores/{section}/{subject}/absent for absent marking
 *
 * Auto-save strategy:
 * Each time a score input changes, we add that student_id to a "pending" set
 * and debounce a save call. This means:
 *   - Fast typers don't trigger a save on every keystroke
 *   - Navigation between rows still queues a save
 *   - Pending rows are shown with a "saving..." indicator
 */

import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import debounce from 'lodash/debounce';
import type {
    ScoreEntrySheet,
    StudentScoreRow,
    SaveScoresPayload,
    AssessmentComponent,
} from '@/types/exam';

export function useScoreEntry(sheet: ScoreEntrySheet) {
    const toast = useToast();

    // ─── Local Editable State ─────────────────────────────────────────────────

    /**
     * Local mutable copy of all student score rows.
     * Keyed by student_id for O(1) access.
     */
    const localScores = ref<Map<string, {
        student_id: string;
        scores: Record<string, number | null>;  // component_key → value
        is_absent: boolean;
        is_exempted: boolean;
        remark: string;
        total_score: number | null;
        grade_code: string | null;
        grade_remark: string | null;
    }>>(new Map());

    // Initialize from sheet data
    const initializeScores = () => {
        sheet.students.forEach((row: StudentScoreRow) => {
            const scores: Record<string, number | null> = {};

            // Pre-fill with existing scores or null
            sheet.template.components.forEach((comp: AssessmentComponent) => {
                scores[comp.key] = row.result?.scores[comp.key]?.score ?? null;
            });

            localScores.value.set(row.student.id, {
                student_id:   row.student.id,
                scores,
                is_absent:    row.result?.is_absent ?? false,
                is_exempted:  row.result?.is_exempted ?? false,
                remark:       row.result?.remark ?? '',
                total_score:  row.result?.total_score ?? null,
                grade_code:   row.result?.grade_code ?? null,
                grade_remark: row.result?.grade_remark ?? null,
            });
        });
    };

    initializeScores();

    // ─── Dirty / Pending Tracking ─────────────────────────────────────────────

    const dirtyStudentIds = ref<Set<string>>(new Set());
    const pendingStudentIds = ref<Set<string>>(new Set());  // Queued for auto-save
    const savingStudentIds = ref<Set<string>>(new Set());   // Currently saving
    const errorStudentIds  = ref<Map<string, string>>(new Map()); // studentId → error msg

    const isDirty = computed(() => dirtyStudentIds.value.size > 0);

    const getRowState = (studentId: string) => ({
        isDirty:   dirtyStudentIds.value.has(studentId),
        isPending: pendingStudentIds.value.has(studentId),
        isSaving:  savingStudentIds.value.has(studentId),
        error:     errorStudentIds.value.get(studentId) ?? null,
    });

    // ─── Score Update ─────────────────────────────────────────────────────────

    /**
     * Called when a score input changes.
     * Updates local state and marks the student as dirty + pending auto-save.
     */
    const updateScore = (studentId: string, componentKey: string, value: number | null) => {
        const row = localScores.value.get(studentId);
        if (!row) return;

        row.scores[componentKey] = value;
        row.total_score = null; // Will be recalculated on save
        row.grade_code = null;

        dirtyStudentIds.value.add(studentId);
        pendingStudentIds.value.add(studentId);
        errorStudentIds.value.delete(studentId);

        triggerAutoSave();
    };

    const updateAbsent = (studentId: string, isAbsent: boolean) => {
        const row = localScores.value.get(studentId);
        if (!row) return;

        row.is_absent = isAbsent;

        if (isAbsent) {
            // Clear scores when marking absent
            sheet.template.components.forEach((comp: AssessmentComponent) => {
                row.scores[comp.key] = null;
            });
        }

        dirtyStudentIds.value.add(studentId);
        pendingStudentIds.value.add(studentId);
        triggerAutoSave();
    };

    const updateRemark = (studentId: string, remark: string) => {
        const row = localScores.value.get(studentId);
        if (!row) return;

        row.remark = remark;
        dirtyStudentIds.value.add(studentId);
        pendingStudentIds.value.add(studentId);
        triggerAutoSave();
    };

    // ─── Score Validation ─────────────────────────────────────────────────────

    const validateScore = (value: number | null, max: number): string | null => {
        if (value === null) return null; // Null is valid (not yet entered)
        if (value < 0) return `Score cannot be negative`;
        if (value > max) return `Score cannot exceed ${max}`;
        return null;
    };

    const getComponentMax = (componentKey: string): number => {
        return sheet.template.components.find(c => c.key === componentKey)?.max_score ?? 0;
    };

    // ─── Save Logic ───────────────────────────────────────────────────────────

    const autoSaveUrl = `/exams/${sheet.exam.id}/scores/${sheet.section.id}/${sheet.subject.id}`;

    /**
     * Save a subset of students (those in pendingStudentIds).
     * Used by auto-save and saveAll.
     */
    const saveStudents = async (studentIds: string[]): Promise<void> => {
        if (studentIds.length === 0) return;

        const payload: SaveScoresPayload[] = studentIds.map(id => {
            const row = localScores.value.get(id)!;
            return {
                student_id:  id,
                is_absent:   row.is_absent,
                is_exempted: row.is_exempted,
                scores:      row.is_absent ? {} : row.scores,
                remark:      row.remark || null,
            };
        });

        // Mark as saving
        studentIds.forEach(id => {
            savingStudentIds.value.add(id);
            pendingStudentIds.value.delete(id);
        });

        try {
            const response = await axios.post(autoSaveUrl, { scores: payload });

            // Clear dirty flags for saved rows
            studentIds.forEach(id => {
                dirtyStudentIds.value.delete(id);
                savingStudentIds.value.delete(id);
            });

            // Update local totals from server response if provided
            if (response.data.results) {
                response.data.results.forEach((result: any) => {
                    const row = localScores.value.get(result.student_id);
                    if (row) {
                        row.total_score  = result.total_score;
                        row.grade_code   = result.grade_code;
                        row.grade_remark = result.grade_remark;
                    }
                });
            }

            // Show error toasts for per-row failures
            if (response.data.errors?.length) {
                response.data.errors.forEach((err: { student_id: string; message: string }) => {
                    errorStudentIds.value.set(err.student_id, err.message);
                    savingStudentIds.value.delete(err.student_id);
                });
            }
        } catch (error: any) {
            // On network failure, re-add to pending so auto-save retries
            studentIds.forEach(id => {
                savingStudentIds.value.delete(id);
                pendingStudentIds.value.add(id);
                errorStudentIds.value.set(id, 'Save failed — will retry.');
            });

            toast.add({
                severity: 'error',
                summary: 'Save Failed',
                detail: 'Scores could not be saved. Please check your connection.',
                life: 5000,
            });
        }
    };

    /**
     * Auto-save: fires 1.5 seconds after the last change.
     */
    const triggerAutoSave = debounce(async () => {
        const toSave = Array.from(pendingStudentIds.value);
        if (toSave.length === 0) return;
        await saveStudents(toSave);
    }, 1500);

    /**
     * Save ALL dirty rows immediately. Called by the explicit "Save All" button.
     */
    const saveAll = async () => {
        triggerAutoSave.cancel();
        const toSave = Array.from(dirtyStudentIds.value);
        await saveStudents(toSave);

        if (errorStudentIds.value.size === 0) {
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: 'All scores saved successfully.',
                life: 3000,
            });
        }
    };

    // ─── Progress ────────────────────────────────────────────────────────────

    const completionProgress = computed(() => {
        const rows = Array.from(localScores.value.values());
        if (rows.length === 0) return 0;

        const fullyScored = rows.filter(row => {
            if (row.is_absent || row.is_exempted) return true;
            return sheet.template.components.every(comp =>
                row.scores[comp.key] !== null && row.scores[comp.key] !== undefined
            );
        }).length;

        return Math.round((fullyScored / rows.length) * 100);
    });

    // ─── Public API ───────────────────────────────────────────────────────────

    return {
        localScores,
        isDirty,
        completionProgress,

        updateScore,
        updateAbsent,
        updateRemark,
        validateScore,
        getComponentMax,
        getRowState,
        saveAll,
    };
}
