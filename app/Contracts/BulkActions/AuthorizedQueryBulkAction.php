<?php

namespace App\Contracts\BulkActions;

use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 6 bulk-action contract: operate on an already-authorized selection query.
 *
 * The Builder MUST already include tenant/school/section/policy constraints and
 * the selection membership (IDs or filters). Handlers must NOT call
 * (new $modelClass)->newQuery() — that would discard non-global authorization.
 */
interface AuthorizedQueryBulkAction
{
    /**
     * Execute against the authorized selection query.
     *
     * @param  Builder  $authorizedSelectionQuery  Scoped + selection-applied builder
     */
    public function handleOnAuthorizedQuery(Builder $authorizedSelectionQuery, mixed $payload = null): BulkActionResult;
}
