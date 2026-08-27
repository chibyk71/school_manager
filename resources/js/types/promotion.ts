/**
 * Promotion module types — batch + student rows for Index / Show / Review pages.
 */

export type PromotionBatchStatus =
    | 'draft'
    | 'pending'
    | 'reviewing'
    | 'approved'
    | 'executing'
    | 'completed'
    | 'cancelled';

export type PromotionDecision = 'promote' | 'repeat' | 'graduate' | 'incomplete';

export interface PromotionBatch {
    id: string;
    name: string;
    description: string | null;
    status: PromotionBatchStatus;
    status_label: string;
    progress_percentage: number;
    total_students: number;
    processed_students: number;
    failed_students: number;
    completed_with_errors?: boolean;
    academic_session: { id: string; name: string } | null;
    initiated_by?: { id: string; name: string } | null;
    approved_by?: { id: string; name: string } | null;
    executed_by?: { id: string; name: string } | null;
    approved_at: string | null;
    executed_at: string | null;
    is_terminal: boolean;
    is_editable: boolean;
    is_ready_for_approval: boolean;
    is_approved: boolean;
    is_executing: boolean;
    is_completed: boolean;
    is_cancelled: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface PromotionStudent {
    id: string;
    student_id: string;
    student?: {
        id: string;
        name: string;
        admission_number?: string | null;
    } | null;
    current_class_section?: { id: string; name: string } | null;
    next_class_section?: { id: string; name: string } | null;
    recommendation: PromotionDecision;
    recommendation_label: string;
    final_decision: PromotionDecision | null;
    final_outcome: PromotionDecision;
    outcome_label: string;
    is_overridden: boolean;
    override_reason: string | null;
    overridden_by?: { id: string; name: string } | null;
    overridden_at: string | null;
    average_score: number | null;
    failed_subjects_count: number | null;
    total_subjects_count: number | null;
    attendance_percentage: number | null;
    is_processed: boolean;
    processed_at: string | null;
    has_processing_error: boolean;
    processing_error?: string | null;
    created_at: string | null;
}

export const PROMOTION_STATUS_CONFIG: Record<
    PromotionBatchStatus,
    { label: string; severity: 'secondary' | 'info' | 'success' | 'warn' | 'danger' | 'contrast'; icon: string }
> = {
    draft: { label: 'Draft', severity: 'secondary', icon: 'pi pi-file' },
    pending: { label: 'Pending Review', severity: 'info', icon: 'pi pi-clock' },
    reviewing: { label: 'Reviewing', severity: 'info', icon: 'pi pi-eye' },
    approved: { label: 'Approved', severity: 'success', icon: 'pi pi-check' },
    executing: { label: 'Executing', severity: 'warn', icon: 'pi pi-spin pi-spinner' },
    completed: { label: 'Completed', severity: 'success', icon: 'pi pi-check-circle' },
    cancelled: { label: 'Cancelled', severity: 'danger', icon: 'pi pi-times-circle' },
};

export const PROMOTION_DECISION_CONFIG: Record<
    PromotionDecision,
    { label: string; severity: 'success' | 'warn' | 'info' | 'secondary' | 'danger' }
> = {
    promote: { label: 'Promote', severity: 'success' },
    repeat: { label: 'Repeat', severity: 'warn' },
    graduate: { label: 'Graduate', severity: 'info' },
    incomplete: { label: 'Incomplete', severity: 'secondary' },
};
