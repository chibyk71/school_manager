<?php

namespace App\Actions\BulkActions;

use App\Contracts\BulkActions\BulkActionHandler;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * DeleteBulkAction.php
 *
 * Concrete implementation of the BulkActionHandler contract for soft (or hard) deletion.
 *
 * This class is responsible ONLY for deleting records. It does not handle validation,
 * transactions, logging, or response formatting — those responsibilities belong to
 * BulkActionService.
 *
 * Features / Problems Solved:
 * - Clean separation of concerns: one class = one action
 * - Supports both soft delete and force delete based on request
 * - Respects model traits (SoftDeletes, BelongsToSchool, etc.)
 * - Throws clear exceptions that are caught and logged by the service
 * - Fully testable in isolation
 *
 * Role in the Bulk Actions Package:
 * - Implements BulkActionHandler interface
 * - Registered in BulkActionRegistry under the key 'delete'
 * - Called by BulkActionService after validation and inside a database transaction
 * - Returns standardized BulkActionResult for consistent controller/frontend responses
 *
 * How it fits into the architecture:
 * 1. BulkActionRequest validates ids[] and force flag
 * 2. BulkActionRegistry resolves 'delete' → DeleteBulkAction
 * 3. BulkActionService starts transaction → calls $this->handle()
 * 4. This action performs the actual delete
 * 5. Service catches exceptions, rolls back, and returns BulkActionResult
 *
 * Extensibility Note:
 * This pattern allows adding ActivateBulkAction, ApproveBulkAction, etc., without touching
 * the service or existing actions.
 */

class DeleteBulkAction implements BulkActionHandler
{
    /**
     * Execute the delete action.
     *
     * @param BulkActionRequest $request
     * @param string $modelClass
     * @return BulkActionResult
     * @throws \Exception
     */
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $ids = $request->getIds();
        $isForce = $request->isForceDelete();

        if (empty($ids)) {
            throw new \Exception('No records selected for deletion.');
        }

        // Instantiate model to use its query builder (respects global scopes like SchoolScope)
        $model = new $modelClass();

        try {
            $query = $model->newQuery()->whereIn('id', $ids);

            $count = $isForce
                ? $query->forceDelete()
                : $query->delete();

            $message = $this->buildSuccessMessage($count, $isForce);

            return BulkActionResult::success(
                action: $this->getName(),
                count: $count,
                message: $message,
                meta: [
                    'force' => $isForce,
                    'model' => $modelClass,
                ]
            );

        } catch (\Exception $e) {
            Log::error('DeleteBulkAction failed', [
                'model' => $modelClass,
                'ids' => $ids,
                'force' => $isForce,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Failed to delete records: " . $e->getMessage());
        }
    }

    /**
     * Return the machine name of this action.
     */
    public function getName(): string
    {
        return 'delete';
    }

    /**
     * Build user-friendly success message.
     */
    private function buildSuccessMessage(int $count, bool $force): string
    {
        $recordWord = $count === 1 ? 'record' : 'records';

        return $force
            ? "{$count} {$recordWord} permanently deleted."
            : "{$count} {$recordWord} moved to trash successfully.";
    }

    /**
     * All models are supported by default.
     */
    public function supports(string $modelClass): bool
    {
        return true;
    }
}
