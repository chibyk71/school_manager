<?php

namespace App\Policies\Promotion;

use App\Models\Promotion\PromotionBatch;
use App\Models\User;
use App\States\Promotion\Approved;
use App\States\Promotion\Pending;
use App\States\Promotion\Reviewing;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    public function view(User $user, PromotionBatch $batch): bool
    {
        return $user->can('promotions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promotions.create');
    }

    public function review(User $user, PromotionBatch $batch): bool
    {
        // Full review (override decisions) while pending/reviewing
        if ($user->can('promotions.review') && ($batch->status instanceof Pending || $batch->status instanceof Reviewing)) {
            return true;
        }

        // Read-only access to the student list after draft for anyone with view
        if ($user->can('promotions.view') && ! $batch->status instanceof \App\States\Promotion\Draft) {
            return true;
        }

        return false;
    }

    public function approve(User $user, PromotionBatch $batch): bool
    {
        if (! $user->can('promotions.approve')) {
            return false;
        }

        return $batch->status instanceof Pending || $batch->status instanceof Reviewing;
    }

    public function execute(User $user, PromotionBatch $batch): bool
    {
        if (! $user->can('promotions.execute')) {
            return false;
        }

        return $batch->status instanceof Approved;
    }

    public function cancel(User $user, PromotionBatch $batch): bool
    {
        if (! $user->can('promotions.cancel')) {
            return false;
        }

        return $batch->status->canBeCancelled();
    }
}
