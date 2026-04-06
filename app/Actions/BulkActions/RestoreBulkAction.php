<?php

namespace App\Actions\BulkActions;

use App\Contracts\BulkActions\BulkActionHandler;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
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

class RestoreBulkAction implements BulkActionHandler
{
    /**
     * Execute the restore action.
     *
     * @param BulkActionRequest $request
     * @param string $modelClass
     * @return BulkActionResult
     * @throws \Exception
     */
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $ids = $request->getIds();

        if (empty($ids)) {
            throw new \Exception('No records selected for restoration.');
        }

        // Instantiate model to use its query builder (respects global scopes)
        $model = new $modelClass();

        try {
            $count = $model->newQuery()
                ->withTrashed()
                ->whereIn('id', $ids)
                ->restore();

            $message = $this->buildSuccessMessage($count);

            return BulkActionResult::success(
                action: $this->getName(),
                count: $count,
                message: $message,
                meta: [
                    'model' => $modelClass,
                ]
            );

        } catch (\Exception $e) {
            Log::error('RestoreBulkAction failed', [
                'model' => $modelClass,
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Failed to restore records: " . $e->getMessage());
        }
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
