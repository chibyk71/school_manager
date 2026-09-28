<?php

namespace App\Contracts\BulkActions;

use App\Http\Requests\BulkActions\BulkActionRequest;
use App\DataTransferObjects\BulkActions\BulkActionResult;

/**
 * Core interface for the Bulk Actions Module.
 *
 * Every concrete handler (DeleteBulkAction, RestoreBulkAction, etc.) must implement this.
 * supports() has no default body here so autoload works on all supported PHP builds;
 * concrete handlers implement supports() (typically returning true).
 */
interface BulkActionHandler
{
    /**
     * Execute the bulk action on the given model.
     *
     * @param  string  $modelClass  Fully qualified Eloquent model class name
     *
     * @throws \Exception When the action fails (caught by the service for rollback + logging)
     */
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult;

    /**
     * Unique machine name of this action (e.g. 'delete', 'restore', 'force_delete').
     */
    public function getName(): string;

    /**
     * Whether this handler supports a particular model.
     * Implementations typically return true for all models.
     */
    public function supports(string $modelClass): bool;
}
