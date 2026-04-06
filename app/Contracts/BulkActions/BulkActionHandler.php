<?php

namespace App\Contracts\BulkActions;

use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Database\Eloquent\Model;

/**
 * BulkActionHandler.php
 *
 * Core interface for the Bulk Actions Module.
 *
 * This contract defines the standard contract that every bulk action handler must follow.
 * It serves as the foundation for extensibility — allowing new bulk actions (activate, approve,
 * export, etc.) to be added later without modifying the BulkActionService or existing code.
 *
 * Features / Problems Solved:
 * - Enforces a consistent API for all bulk operations
 * - Enables the Registry pattern (BulkActionRegistry) to dynamically resolve handlers
 * - Supports dependency injection and testability (each action can be unit tested in isolation)
 * - Keeps the service thin and focused on orchestration, transaction, and logging
 * - Follows Single Responsibility Principle — each action only knows how to perform its own logic
 *
 * Role in the Bulk Actions Package:
 * - Implemented by all concrete action classes (DeleteBulkAction, RestoreBulkAction, ForceDeleteBulkAction, etc.)
 * - Used by BulkActionRegistry to map action names → handler classes
 * - Injected into BulkActionService which calls handle() after validation and transaction setup
 *
 * How it fits into the overall architecture:
 * 1. BulkActionRequest validates incoming data
 * 2. BulkActionRegistry resolves the correct handler based on 'action'
 * 3. BulkActionService executes the handler inside a DB transaction + logging
 * 4. Handler returns a standardized BulkActionResult
 *
 * Extensibility:
 * To add a new action (e.g. 'activate'), simply:
 *   - Create ActivateBulkAction implementing this interface
 *   - Register it in BulkActionRegistry
 *   - No changes needed to BulkActionService or existing controllers
 */

interface BulkActionHandler
{
    /**
     * Execute the bulk action on the given model.
     *
     * @param BulkActionRequest $request The validated bulk request containing ids and options
     * @param string $modelClass Fully qualified Eloquent model class name (e.g. App\Models\Student::class)
     * @return BulkActionResult Standardized result object
     *
     * @throws \Exception When the action fails (will be caught by the service for rollback + logging)
     */
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult;

    /**
     * Return the unique machine name of this action (e.g. 'delete', 'restore', 'force_delete').
     *
     * Used by the registry for mapping and by the service for logging.
     */
    public function getName(): string;

    /**
     * Optional: Check if this handler supports a particular model.
     * Useful for future actions that only apply to certain resource types.
     *
     * Default implementation returns true for all models.
     */
    public function supports(string $modelClass): bool
    {
        return true;
    }
}
