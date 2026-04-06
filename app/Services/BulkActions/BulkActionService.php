<?php

namespace App\Services\BulkActions;

use App\Contracts\BulkActions\BulkActionHandler;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * BulkActionService.php
 *
 * Main orchestrator for the entire Bulk Actions Module.
 *
 * This service is responsible for coordinating bulk operations while keeping controllers
 * extremely thin. It handles validation (via request), resolution of the correct handler
 * (via registry), database transactions, comprehensive logging, and standardized responses.
 *
 * Features / Problems Solved:
 * - Centralizes all bulk operation logic in one place
 * - Ensures every bulk action runs inside a database transaction (atomicity)
 * - Uses the registry to dynamically resolve handlers → highly extensible
 * - Provides consistent error handling and detailed production logging
 * - Returns standardized BulkActionResult for easy consumption by controllers
 * - Supports multi-tenant models via global scopes (BelongsToSchool, etc.)
 * - Clean separation: Service orchestrates, Handlers execute specific logic
 *
 * Role in the Bulk Actions Package:
 * - The public API that controllers should call
 * - Uses BulkActionRegistry to resolve handlers
 * - Calls BulkActionHandler::handle() inside a transaction
 * - Converts exceptions into friendly BulkActionResult failures
 * - Works seamlessly with your frontend composables (useDeleteResource, useRestoreResource)
 *
 * How it fits into the architecture:
 * 1. Controller injects BulkActionRequest + BulkActionService
 * 2. Service validates action via registry
 * 3. Service starts DB transaction
 * 4. Registry resolves handler → handler performs the actual work
 * 5. Service returns BulkActionResult (success or failure)
 *
 * Extensibility:
 * Adding a new action (e.g. 'activate') only requires:
 *   - Creating the handler class
 *   - Registering it in BulkActionRegistry
 *   - No changes to this service.
 */

class BulkActionService
{
    /**
     * The registry used to resolve action handlers.
     */
    protected BulkActionRegistry $registry;

    /**
     * Create a new BulkActionService instance.
     */
    public function __construct(BulkActionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Execute a bulk action on the given model.
     *
     * This is the main public method controllers should call.
     *
     * @param BulkActionRequest $request Validated bulk request
     * @param string $modelClass Fully qualified Eloquent model class (e.g. App\Models\Student::class)
     * @return BulkActionResult Standardized result
     * @throws Exception Only for critical failures (most errors are returned as failure result)
     */
    public function execute(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $action = $request->getAction();
        $ids = $request->getIds();

        // Early validation
        if (empty($ids)) {
            return BulkActionResult::failure(
                action: $action,
                message: 'No records selected for this bulk action.',
                meta: ['model' => $modelClass]
            );
        }

        // Verify model class is valid
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, \Illuminate\Database\Eloquent\Model::class)) {
            return BulkActionResult::failure(
                action: $action,
                message: 'Invalid model class provided.',
                meta: ['model' => $modelClass]
            );
        }

        try {
            return DB::transaction(function () use ($request, $modelClass, $action) {

                $handler = $this->registry->resolve($action);

                // Optional: Check if handler supports this model (future-proof)
                if (!$handler->supports($modelClass)) {
                    throw new Exception("Action '{$action}' is not supported for model {$modelClass}");
                }

                $result = $handler->handle($request, $modelClass);

                // Log successful action
                $this->logSuccess($action, $modelClass, $request->getCount(), $result);

                return $result;
            });

        } catch (Exception $e) {
            // Log the failure with full context
            $this->logFailure($action, $modelClass, $request->getIds(), $e);

            // Return a friendly failure result instead of throwing (unless it's critical)
            return BulkActionResult::failure(
                action: $action,
                message: $this->getUserFriendlyErrorMessage($e, $action),
                meta: [
                    'model' => $modelClass,
                    'ids_count' => count($request->getIds()),
                ]
            );
        }
    }

    /**
     * Convenience method for delete action.
     */
    public function delete(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        return $this->execute($request, $modelClass);
    }

    /**
     * Convenience method for restore action.
     */
    public function restore(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $request->merge(['action' => 'restore']); // Ensure correct action name
        return $this->execute($request, $modelClass);
    }

    /**
     * Convenience method for force delete.
     */
    public function forceDelete(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        $request->merge(['action' => 'force_delete']);
        return $this->execute($request, $modelClass);
    }

    /**
     * Log successful bulk action.
     */
    protected function logSuccess(string $action, string $modelClass, int $count, BulkActionResult $result): void
    {
        Log::info("Bulk action completed successfully", [
            'action' => $action,
            'model' => $modelClass,
            'count' => $count,
            'user_id' => auth()->id(),
            'school_id' => GetSchoolModel()?->id ?? null,
            'meta' => $result->meta,
        ]);
    }

    /**
     * Log failed bulk action with detailed context.
     */
    protected function logFailure(string $action, string $modelClass, array $ids, Exception $exception): void
    {
        Log::error("Bulk action failed", [
            'action' => $action,
            'model' => $modelClass,
            'ids' => $ids,
            'user_id' => auth()->id(),
            'school_id' => GetSchoolModel()?->id ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Convert technical exceptions into user-friendly messages.
     */
    protected function getUserFriendlyErrorMessage(Exception $e, string $action): string
    {
        $base = match ($action) {
            'restore' => 'Failed to restore the selected records.',
            'force_delete' => 'Failed to permanently delete the selected records.',
            default => 'Failed to delete the selected records.',
        };

        // You can make messages more specific based on exception type if needed
        return $base . ' Please try again or contact support if the problem persists.';
    }
}
