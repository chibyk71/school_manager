<?php

namespace App\Policies\Promotion;

use App\Models\Promotion\PromotionBatch;
use App\Models\User;

/**
 * PromotionPolicy v1.0 – Authorization Policy for Promotion Module
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Handles all authorization checks for the Promotion module using pure permission-based logic
 * (consistent with the rest of the codebase – no hardcoded role names).
 *
 * Relevant Permissions (should be seeded):
 * - promotions.view
 * - promotions.create
 * - promotions.review          (for overriding student decisions)
 * - promotions.approve
 * - promotions.execute
 * - promotions.cancel
 *
 * Key Design Decisions:
 * - All methods are permission-based (using permitted() helper or $user->can())
 * - Batch-level checks consider current status where appropriate
 * - View policy is permissive for listing but strict for sensitive actions
 * - System admins bypass via existing CustomUserChecker (no special handling needed here)
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController for all CRUD + state transition actions
 * - Enforced in PromotionService for critical operations (approve, execute, cancel)
 * - Frontend will also check these permissions before showing action buttons/modals
 *
 * Production-Ready Features:
 * - Consistent with other policies in the codebase
 * - Clear, well-documented methods matching controller actions
 * - Safe null checks and status-aware logic
 */

class PromotionPolicy
{
    /**
     * Determine whether the user can view any promotion batches.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    /**
     * Determine whether the user can view a specific promotion batch.
     */
    public function view(User $user, PromotionBatch $batch): bool
    {
        return $user->can('promotions.view');
    }

    /**
     * Determine whether the user can create a new promotion batch.
     */
    public function create(User $user): bool
    {
        return $user->can('promotions.create');
    }

    /**
     * Determine whether the user can review/override student decisions in a batch.
     * Typically allowed during 'pending' or 'reviewing' status.
     */
    public function review(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.review')) {
            return false;
        }

        return in_array($batch->status, ['pending', 'reviewing'], true);
    }

    /**
     * Determine whether the user can approve a batch (move to 'approved').
     * Allowed when batch is in 'pending' or 'reviewing' state.
     */
    public function approve(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.approve')) {
            return false;
        }

        return in_array($batch->status, ['pending', 'reviewing'], true);
    }

    /**
     * Determine whether the user can execute an approved batch.
     * Only allowed when status is exactly 'approved'.
     */
    public function execute(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.execute')) {
            return false;
        }

        return $batch->status === 'approved';
    }

    /**
     * Determine whether the user can cancel a batch.
     * Allowed for non-terminal states.
     */
    public function cancel(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.cancel')) {
            return false;
        }

        return !in_array($batch->status, ['completed', 'cancelled'], true);
    }

    /**
     * Determine whether the user can update a batch (general update).
     * Currently restricted to non-terminal batches.
     */
    public function update(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.create')) { // reuse create permission for basic edits
            return false;
        }

        return !in_array($batch->status, ['completed', 'cancelled'], true);
    }

    /**
     * Determine whether the user can delete a batch.
     * Soft delete is allowed only for non-terminal batches (usually cancelled ones).
     */
    public function delete(User $user, PromotionBatch $batch): bool
    {
        if (!$user->can('promotions.cancel')) {
            return false;
        }

        return in_array($batch->status, ['draft', 'pending', 'reviewing', 'cancelled'], true);
    }

    /**
     * Determine whether the user can restore a soft-deleted batch.
     */
    public function restore(User $user, PromotionBatch $batch): bool
    {
        return $user->can('promotions.create'); // Usually same as create permission
    }

    /**
     * Determine whether the user can permanently delete a batch.
     * Generally discouraged – use soft delete instead.
     */
    public function forceDelete(User $user, PromotionBatch $batch): bool
    {
        return false; // Never allow force delete on promotion batches
    }
}
