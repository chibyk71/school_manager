/**
 * resources/js/types/exam.ts
 *
 * Single source of truth for all TypeScript types in the Exam & Test module.
 *
 * Mirrors backend shapes exactly:
 *   Exam                    ↔ ExamResource::toArray()
 *   AssessmentTemplate      ↔ AssessmentTemplateResource::toArray()
 *   ExamResult              ↔ ExamResult model
 *   ComputedResult          ↔ ComputedResult model
 *   ScoreEntrySheet         ↔ ScoreEntryService::getScoreEntrySheet()
 *
 * Used by:
 *   - Academic/Exams/Index.vue         (exam list)
 *   - Academic/Exams/Show.vue          (exam hub)
 *   - Academic/Exams/ScoreEntry.vue    (score entry form)
 *   - Academic/Exams/Results.vue       (results view)
 *   - Academic/Exams/ReportCard.vue    (report card)
 */

// ──────────────────────────────────────────────────────────────────────────────
// Assessment Template
// ──────────────────────────────────────────────────────────────────────────────

export interface AssessmentComponent {
    key: string;            // e.g., 'ca1', 'ca2', 'exam'
    label: string;          // e.g., '1st CA', 'Exam'
    max_score: number;      // e.g., 20, 60
    weight_percent: number; // e.g., 20, 60 (must sum to 100)
    is_exam: boolean;       // true = main examination paper
    sort_order: number;
}

export interface AssessmentTemplate {
    id: string;
    name: string;
    description: string | null;
    components: AssessmentComponent[];
    total_score: number;         // usually 100
    pass_mark: number;           // e.g., 40
    is_default: boolean;
    is_active: boolean;
    school_section_id: string | null;
    school_section_name?: string | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Exam
// ──────────────────────────────────────────────────────────────────────────────

export type ExamStatus =
    | 'draft'
    | 'published'
    | 'ongoing'
    | 'completed'
    | 'results_approved';

export interface Exam {
    id: string;
    name: string;
    description: string | null;
    status: ExamStatus;

    // Academic context
    academic_session_id: string;
    session_name?: string;
    term_id: string | null;
    term_name?: string | null;

    // Class scope
    class_level_id: string | null;
    level_name?: string | null;
    class_section_id: string | null;
    section_name?: string | null;

    // Assessment template
    assessment_template_id: string;
    template_name?: string;
    template?: AssessmentTemplate | null;

    // Dates
    exam_start_date: string | null;
    exam_end_date: string | null;
    published_at: string | null;
    results_published_at: string | null;
    locked_at: string | null;

    // Computed flags (from resource)
    is_editable: boolean;
    is_locked: boolean;
    can_compute_results: boolean;
    score_entry_progress: number; // 0–100 percentage

    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface ExamFormData {
    name: string;
    description?: string | null;
    academic_session_id: string;
    term_id?: string | null;
    class_level_id?: string | null;
    class_section_id?: string | null;
    assessment_template_id: string;
    exam_start_date?: string | null;
    exam_end_date?: string | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Score Entry
// ──────────────────────────────────────────────────────────────────────────────

/** Per-component score value (stored as JSON in exam_results.scores) */
export interface ComponentScore {
    score: number | null;
    max: number;
    entered_at: string | null;
}

/** A single student's scores across all components of one subject exam */
export interface StudentScoreRow {
    student: {
        id: string;
        full_name: string;
        admission_number: string | null;
    };
    result: {
        id: string;
        scores: Record<string, ComponentScore>;  // keyed by component key
        total_score: number | null;
        grade_code: string | null;
        grade_remark: string | null;
        is_absent: boolean;
        is_exempted: boolean;
        remark: string | null;
        is_locked: boolean;
    } | null;
    can_edit: boolean;
}

/** The complete data for the score-entry page */
export interface ScoreEntrySheet {
    exam: {
        id: string;
        name: string;
        status: ExamStatus;
        is_editable: boolean;
    };
    subject: {
        id: string;
        name: string;
        code: string | null;
    };
    template: {
        id: string;
        components: AssessmentComponent[];
        total_score: number;
        pass_mark: number;
    };
    students: StudentScoreRow[];
    section: {
        id: string;
        display_name: string;
    };
}

/** Subject progress item for the score-entry sidebar */
export interface SubjectProgress {
    id: string;
    name: string;
    code: string | null;
    total_students: number;
    scores_entered: number;
    is_complete: boolean;
}

/** Payload sent when saving scores */
export interface SaveScoresPayload {
    student_id: string;
    is_absent: boolean;
    is_exempted: boolean;
    scores: Record<string, number | null>;  // component_key → score value
    remark?: string | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Results
// ──────────────────────────────────────────────────────────────────────────────

export interface SubjectBreakdownItem {
    subject_id: string;
    subject_name: string;
    subject_code: string | null;
    total_score: number | null;
    grade_code: string | null;
    grade_remark: string | null;
    is_absent: boolean;
    is_exempted: boolean;
    class_average: number | null;
    highest_score: number | null;
    lowest_score: number | null;
    position_in_class: number | null;
}

export interface ComputedResult {
    id: string;
    student_id: string;
    exam_id: string;
    class_section_id: string | null;

    // Aggregates
    total_score_obtained: number;
    total_score_possible: number;
    average_score: number;

    // Subject counts
    subjects_count: number;
    subjects_scored: number;
    subjects_passed: number;
    subjects_failed: number;

    // Positions
    position_in_class: number | null;
    position_in_level: number | null;
    class_size: number | null;

    // Subject breakdown (frozen snapshot)
    subject_breakdown: SubjectBreakdownItem[];

    // Remarks
    class_teacher_remark: string | null;
    principal_remark: string | null;

    // Promotion
    promotion_status: 'pending' | 'promoted' | 'repeated' | 'probation' | 'graduated';
    is_final: boolean;
    computed_at: string | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Section Progress (for the Exam show page)
// ──────────────────────────────────────────────────────────────────────────────

export interface SectionProgress {
    section: {
        id: string;
        display_name: string;
    };
    total_students: number;
    scores_entered: number;
    progress: number; // 0–100
}

// ──────────────────────────────────────────────────────────────────────────────
// Exam Status Config for UI
// ──────────────────────────────────────────────────────────────────────────────

export const EXAM_STATUS_CONFIG: Record<ExamStatus, {
    label: string;
    severity: 'secondary' | 'info' | 'warn' | 'success' | 'danger';
    icon: string;
}> = {
    draft:            { label: 'Draft',           severity: 'secondary', icon: 'pi pi-pencil' },
    published:        { label: 'Published',       severity: 'info',      icon: 'pi pi-eye' },
    ongoing:          { label: 'Ongoing',         severity: 'warn',      icon: 'pi pi-play' },
    completed:        { label: 'Completed',       severity: 'success',   icon: 'pi pi-check' },
    results_approved: { label: 'Results Approved',severity: 'success',   icon: 'pi pi-lock' },
};

/** Valid status transitions for each current status */
export const EXAM_STATUS_TRANSITIONS: Record<ExamStatus, ExamStatus[]> = {
    draft:            ['published'],
    published:        ['ongoing', 'draft'],
    ongoing:          ['completed'],
    completed:        ['results_approved'],
    results_approved: [],
};
