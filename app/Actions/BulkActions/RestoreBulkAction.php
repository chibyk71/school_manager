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
 * RestoreBulkAction.php
 *
 * Concrete implementation of the BulkActionHandler contract for restoring soft-deleted records.
 *
 * This class is responsible ONLY for restoring records from the trash. It does not handle
 * validation, transactions, logging, or response formatting — those responsibilities belong
 * to BulkActionService.
 *
 * Features / Problems Solved:
 * - Clean separation of concerns: one class = one action
 * - Uses withTrashed() to properly target soft-deleted records
 * - Respects model traits (SoftDeletes, BelongsToSchool, SchoolScope, etc.)
 * - Throws clear exceptions that are caught and logged by the service
 * - Fully testable in isolation
 *
 * Role in the Bulk Actions Package:
 * - Implements BulkActionHandler interface
 * - Registered in BulkActionRegistry under the key 'restore'
 * - Called by BulkActionService after validation and inside a database transaction
 * - Returns standardized BulkActionResult for consistent controller/frontend responses
 *
 * How it fits into the architecture:
 * 1. BulkActionRequest validates ids[]
 * 2. BulkActionRegistry resolves 'restore' → RestoreBulkAction
 * 3. BulkActionService starts transaction → calls $this->handle()
 * 4. This action performs the actual restore
 * 5. Service catches exceptions, rolls back if needed, and returns BulkActionResult
 *
 * Extensibility Note:
 * This same pattern will be used later for ActivateBulkAction, ApproveBulkAction, etc.
 */

class RestoreBulkAction implements BulkActionHandler, AuthorizedQueryBulkAction
{
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $ids = $request->getIds();

        if (empty($ids)) {
            throw new \Exception('No records selected for restoration.');
        }

        $model = new $modelClass();

        try {
            $query = $model->newQuery()->withTrashed()->whereIn('id', $ids);

            return $this->executeRestore($query, $modelClass);
        } catch (\Exception $e) {
            Log::error('RestoreBulkAction failed', [
                'model' => $modelClass,
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to restore records: '.$e->getMessage());
        }
    }

    public function handleOnAuthorizedQuery(Builder $authorizedSelectionQuery, mixed $payload = null): BulkActionResult
    {
        $modelClass = $authorizedSelectionQuery->getModel()::class;

        try {
            $query = (clone $authorizedSelectionQuery)->withTrashed();

            return $this->executeRestore($query, $modelClass);
        } catch (\Exception $e) {
            Log::error('RestoreBulkAction (authorized) failed', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to restore records: '.$e->getMessage());
        }
    }

    private function executeRestore(Builder $query, string $modelClass): BulkActionResult
    {
        $count = $query->restore();

        return BulkActionResult::success(
            action: $this->getName(),
            count: $count,
            message: $this->buildSuccessMessage($count),
            meta: ['model' => $modelClass]
        );
    }

    /**
     * Return the machine name of this action.
     */
    public function getName(): string
    {
        return 'restore';
    }

    /**
     * Build user-friendly success message.
     */
    private function buildSuccessMessage(int $count): string
    {
        $recordWord = $count === 1 ? 'record' : 'records';
        return "{$count} {$recordWord} restored successfully.";
    }

    /**
     * All models are supported by default.
     */
    public function supports(string $modelClass): bool
    {
        return true;
    }
}
