<?php

namespace App\Actions\BulkActions;

use App\Contracts\BulkActions\BulkActionHandler;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
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

class ForceDeleteBulkAction implements BulkActionHandler
{
    /**
     * Execute the force delete action.
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
            throw new \Exception('No records selected for permanent deletion.');
        }

        // Instantiate model to use its query builder (respects global scopes)
        $model = new $modelClass();

        try {
            $count = $model->newQuery()
                ->withTrashed()
                ->whereIn('id', $ids)
                ->forceDelete();

            $message = $this->buildSuccessMessage($count);

            return BulkActionResult::success(
                action: $this->getName(),
                count: $count,
                message: $message,
                meta: [
                    'force' => true,
                    'model' => $modelClass,
                ]
            );

        } catch (\Exception $e) {
            Log::error('ForceDeleteBulkAction failed', [
                'model' => $modelClass,
                'ids'   => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Failed to permanently delete records: " . $e->getMessage());
        }
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
