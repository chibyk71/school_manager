<?php

namespace App\Actions\BulkActions;

use App\Contracts\BulkActions\AuthorizedQueryBulkAction;
use App\Contracts\BulkActions\BulkActionHandler;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * ForceDeleteBulkAction.php
 *
 * Concrete implementation of the BulkActionHandler contract for permanent (force) deletion.
 *
 * This class is responsible ONLY for permanently removing records from the database
 * (including soft-deleted ones). It does not handle validation, transactions, logging,
 * or response formatting — those responsibilities belong to BulkActionService.
 *
 * Features / Problems Solved:
 * - Clean separation of concerns: one class = one action
 * - Uses withTrashed() + forceDelete() to permanently remove records
 * - Respects model traits (SoftDeletes, BelongsToSchool, SchoolScope, etc.)
 * - Provides clear distinction from regular soft delete
 * - Throws clear exceptions that are caught and logged by the service
 * - Fully testable in isolation
 *
 * Role in the Bulk Actions Package:
 * - Implements BulkActionHandler interface
 * - Registered in BulkActionRegistry under the key 'force_delete'
 * - Called by BulkActionService after validation and inside a database transaction
 * - Returns standardized BulkActionResult for consistent controller/frontend responses
 *
 * How it fits into the architecture:
 * 1. BulkActionRequest validates ids[] and force flag
 * 2. BulkActionRegistry resolves 'force_delete' → ForceDeleteBulkAction
 * 3. BulkActionService starts transaction → calls $this->handle()
 * 4. This action performs the actual force delete
 * 5. Service catches exceptions, rolls back if needed, and returns BulkActionResult
 *
 * Extensibility Note:
 * This same clean pattern will be used later when adding actions like 'activate',
 * 'deactivate', 'approve', etc.
 */

class ForceDeleteBulkAction implements BulkActionHandler, AuthorizedQueryBulkAction
{
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $ids = $request->getIds();

        if (empty($ids)) {
            throw new \Exception('No records selected for permanent deletion.');
        }

        $model = new $modelClass();

        try {
            $query = $model->newQuery()->withTrashed()->whereIn('id', $ids);

            return $this->executeForceDelete($query, $modelClass);
        } catch (\Exception $e) {
            Log::error('ForceDeleteBulkAction failed', [
                'model' => $modelClass,
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to permanently delete records: '.$e->getMessage());
        }
    }

    public function handleOnAuthorizedQuery(Builder $authorizedSelectionQuery, mixed $payload = null): BulkActionResult
    {
        $modelClass = $authorizedSelectionQuery->getModel()::class;

        try {
            $query = (clone $authorizedSelectionQuery)->withTrashed();

            return $this->executeForceDelete($query, $modelClass);
        } catch (\Exception $e) {
            Log::error('ForceDeleteBulkAction (authorized) failed', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to permanently delete records: '.$e->getMessage());
        }
    }

    private function executeForceDelete(Builder $query, string $modelClass): BulkActionResult
    {
        $count = $query->forceDelete();

        return BulkActionResult::success(
            action: $this->getName(),
            count: $count,
            message: $this->buildSuccessMessage($count),
            meta: [
                'force' => true,
                'model' => $modelClass,
            ]
        );
    }

    /**
     * Return the machine name of this action.
     */
    public function getName(): string
    {
        return 'force_delete';
    }

    /**
     * Build user-friendly success message.
     */
    private function buildSuccessMessage(int $count): string
    {
        $recordWord = $count === 1 ? 'record' : 'records';
        return "{$count} {$recordWord} permanently deleted from the system.";
    }

    /**
     * All models are supported by default.
     */
    public function supports(string $modelClass): bool
    {
        return true;
    }
}
